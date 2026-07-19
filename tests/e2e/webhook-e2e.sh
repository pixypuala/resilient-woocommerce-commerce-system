#!/usr/bin/env bash
#
# Live end-to-end test of the webhook inbox against a running WordPress.
#
# Boot a site with the plugin active and the signing secret configured (see
# .wp-env.json), then run this script. It exercises the real REST endpoint —
# signature, replay window, and atomic dedup — over HTTP, asserting the exact
# status codes and JSON results the InboxResult enum maps to. Any mismatch
# fails the script (non-zero exit), so it is safe to run in CI.
#
# Usage:
#   RC_BASE_URL=http://localhost:8888 \
#   RC_SECRET=webhook-e2e-test-secret-value \
#   tests/e2e/webhook-e2e.sh
#
set -euo pipefail

BASE_URL="${RC_BASE_URL:-http://localhost:8888}"
SECRET="${RC_SECRET:-webhook-e2e-test-secret-value}"
ENDPOINT="${BASE_URL}/wp-json/resilient-commerce/v1/webhook"

pass=0
fail=0

# hmac_sha256 <body> -> hex digest, matching hash_hmac('sha256', body, secret).
hmac_sha256() {
	printf '%s' "$1" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //'
}

# post <event_id> <timestamp> <signature> <body> -> "<http_status> <json_body>\n"
post() {
	local event_id="$1" ts="$2" sig="$3" body="$4"
	curl -sS -o /tmp/rc_e2e_body -w '%{http_code}' \
		-X POST "$ENDPOINT" \
		-H 'Content-Type: application/json' \
		-H "X-Rc-Signature: ${sig}" \
		-H "X-Rc-Event-Id: ${event_id}" \
		-H "X-Rc-Timestamp: ${ts}" \
		--data-binary "$body"
	printf ' '
	cat /tmp/rc_e2e_body
	printf '\n'
}

# expect <label> <actual_status> <want_status> <actual_body> <want_result_substr>
expect() {
	local label="$1" got_status="$2" want_status="$3" got_body="$4" want_result="$5"
	if [ "$got_status" = "$want_status" ] && printf '%s' "$got_body" | grep -q "\"result\":\"${want_result}\""; then
		echo "  PASS  ${label}  (HTTP ${got_status}, result=${want_result})"
		pass=$((pass + 1))
	else
		echo "  FAIL  ${label}  got HTTP ${got_status} body=${got_body} (wanted ${want_status}/${want_result})"
		fail=$((fail + 1))
	fi
}

now="$(date +%s)"
body='{"type":"order.updated","status":"wc-processing"}'
sig="$(hmac_sha256 "$body")"

echo "Endpoint: ${ENDPOINT}"

# 1. Valid, fresh delivery -> Accepted (200).
eid="evt-$(date +%s)-$$"
read -r st bd < <(post "$eid" "$now" "$sig" "$body")
expect "valid delivery accepted" "$st" "200" "$bd" "accepted"

# 2. Exact redelivery of the same event id -> Duplicate (200), idempotent.
read -r st bd < <(post "$eid" "$now" "$sig" "$body")
expect "redelivery is idempotent duplicate" "$st" "200" "$bd" "duplicate"

# 3. Tampered signature -> InvalidSignature (401), handler never runs.
read -r st bd < <(post "evt-bad-$$" "$now" "deadbeef" "$body")
expect "bad signature rejected" "$st" "401" "$bd" "invalid_signature"

# 4. Timestamp outside the replay window -> Stale (408).
stale=$((now - 1000))
read -r st bd < <(post "evt-stale-$$" "$stale" "$sig" "$body")
expect "stale timestamp rejected" "$st" "408" "$bd" "stale"

echo ""
echo "E2E result: ${pass} passed, ${fail} failed"
[ "$fail" -eq 0 ]
