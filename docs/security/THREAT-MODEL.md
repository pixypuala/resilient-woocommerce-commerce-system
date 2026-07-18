# Threat Model

> Canvas §24. Scoped to what is **actually implemented** at commit
> `83dd05b`. Mitigations below cite real code; residual risks are stated
> honestly and marked "not executed" where no test proves them.

## Assets being protected

- **Order and refund integrity** — the guarantee that an inbound webhook causes
  its side effect *exactly once* (no duplicate refunds, no double fulfilment).
- **Inventory correctness** — the invariant that stock is never reserved below
  zero (no oversell).
- **The webhook signing secret** (`RESILIENT_COMMERCE_WEBHOOK_SECRET`).
- **Order-state validity** — no illegal lifecycle transition corrupts state.
- **The dedup ledger** (`{prefix}rc_processed_events`) integrity.

## Trust boundaries

| Boundary | Trusted side | Untrusted side |
| --- | --- | --- |
| Public REST endpoint | The plugin's PHP process, `$wpdb` | The internet: anyone can `POST /resilient-commerce/v1/webhook` |
| HMAC verification | Body + secret held server-side | The request body, signature header, event-id header, timestamp header |
| Database | Prefixed table name (SQL identifier, developer-controlled) | `event_id` value (bound as a parameter) |

The single most important boundary: **the webhook route is publicly reachable
(`permission_callback => '__return_true'`)**. Authentication is *not* delegated
to WordPress auth; it is the HMAC signature verified inside the inbox. This is
deliberate — a payment provider cannot present a WordPress cookie or nonce.

## Untrusted input

Everything on an inbound request:

- Raw request body (`$request->get_body()`).
- `X-Rc-Signature` header.
- `X-Rc-Event-Id` header.
- `X-Rc-Timestamp` header (cast to `int`).

None of these is trusted until the inbox gauntlet passes.

## User types

- **Anonymous internet / webhook sender** — the only actor that reaches the
  endpoint. A legitimate sender holds the shared secret; an attacker does not.
- **Administrator** — defines the secret constant (in `wp-config.php` / env),
  activates the plugin (creates the dedup table). No admin UI beyond that.
- **Editors / customers** — no direct interaction with this plugin's surfaces.
- **External service** — the payment/commerce provider posting signed events.

## Attack surfaces

1. **`POST /resilient-commerce/v1/webhook`** — the public REST route (primary).
2. **The dedup `INSERT`** — the only dynamic SQL path.
3. **The `resilient_commerce_webhook` action** — downstream side-effect
   subscribers (out of scope for this core, but the contract is a surface).

## Data flows

```
Internet ──POST──▶ WebhookController::handle
                     │ extract raw body + 3 headers
                     ▼
                 WebhookInbox::receive
                     │ 1. SignatureVerifier::verify (constant-time HMAC)   ── fail ▶ 401 InvalidSignature
                     │ 2. event_id/timestamp present                       ── fail ▶ 400 Malformed
                     │ 3. |now - timestamp| ≤ replay_window (300s)         ── fail ▶ 408 Stale
                     │ 4. WpdbEventStore::claim (atomic UNIQUE INSERT)     ── lost ▶ 200 Duplicate (no-op)
                     ▼ all pass, exactly once
                 do_action( 'resilient_commerce_webhook', $body, $event_id ) ─▶ 200 Accepted
```

## Threat table

| # | Threat | Likelihood | Impact | Existing mitigation (code) | Residual risk |
| --- | --- | --- | --- | --- | --- |
| T1 | **Forged webhook** — attacker posts a fake order/refund event | High | Critical | `SignatureVerifier::verify` recomputes HMAC-SHA256 over the raw body and compares with `hash_equals` (constant-time). Empty/unknown-algorithm secrets throw at construction. No signature ⇒ no endpoint (bootstrap fail-safe). | Depends on secret confidentiality and sender using the same raw-body bytes. **Not executed:** no live provider round-trip test. |
| T2 | **Signature timing oracle** — leak the expected HMAC via response timing | Low | High | `hash_equals` never short-circuits on first differing byte. | Covered by unit test asserting match/mismatch; micro-timing not independently profiled (low residual). |
| T3 | **Replay** — attacker captures a valid delivery and resends it later | Medium | High | Freshness gate rejects events whose timestamp is outside ±`replay_window` (300s). | A replay *within* the 300s window still relies on T4 (dedup) to be a no-op. Window length is a fixed default, not operator-tunable via UI. |
| T4 | **Duplicate delivery / double side effect** — provider redelivers, or two workers process concurrently | High | Critical | `WpdbEventStore::claim` is a single `INSERT` against a `UNIQUE(event_id)` column; the DB arbitrates the race, the loser gets `Duplicate` (idempotent 200). Inbox runs the handler only on a won claim. | **Not executed against a real MySQL:** the atomic-race guarantee is proven by design + in-memory analogue in unit tests, not by a concurrent DB integration test. |
| T5 | **SQL injection** via `event_id` | Low | Critical | `event_id` is bound with `$wpdb->prepare( ... %s )` / `$wpdb->insert( ..., array( '%s' ) )`. The only interpolated token is the trusted, prefixed table name (an SQL identifier, which cannot be a bound parameter). | No user-controlled identifiers, ordering, or `LIKE` anywhere. Residual: negligible. |
| T6 | **Malformed input crashes the handler** | Medium | Medium | Missing/empty `event_id` or non-positive `timestamp` ⇒ `Malformed` (400) before any side effect. Headers are cast (`(string)`, `(int)`). | No body-size cap enforced by the plugin (relies on server/WP limits). |
| T7 | **Unauthorized state transition** — event drives an illegal order status | Medium | High | `OrderStateMachine::transition_to` rejects transitions not in the conservative WooCommerce-aligned table with `OrderException`; same-status is an idempotent no-op. Terminal states allow no onward move. | Rules unit-tested; **not** exercised against live `WC_Order` objects (documented boundary). |
| T8 | **Oversell** — concurrent checkouts both take the last unit | Medium | High | `StockLedger::reserve` computes availability as on-hand minus live reservations and refuses to reserve above it; expired holds reclaimed via injected clock. | In-memory, single-process model. Distributed/DB-backed reservation locking is **not implemented** — the persistence layer for stock is a documented boundary. |
| T9 | **Secret disclosure in source or logs** | Low | Critical | Secret is read from a constant/env only (`signing_secret()`); never hard-coded. No logging of body, secret, or signature in this core. | No structured logging exists at all yet, so no redaction policy is *tested*. |
| T10 | **Denial of service** — flood of invalid-signature requests | Medium | Medium | Each request is rejected cheaply (one HMAC compute) before any DB write. | **No rate limiting / throttling** in the plugin. Residual accepted; mitigation is edge/host-level. |
| T11 | **Unbounded ledger growth** | Low | Low | — | No pruning of old `event_id` rows. Accepted at expected volume; no retention job. |
| T12 | **Supply-chain** — malicious dependency | Low | High | Runtime deps are only `ext-json`; all others are dev-only. `composer.lock` committed. | No automated dependency audit / secret scanning stage in CI yet. |

## Boundaries explicitly out of scope here

- **Multisite / tenant isolation** — not implemented or tested. The dedup table
  uses the site `$wpdb->prefix`, so per-site isolation follows from that, but
  network-activation behaviour is unverified. Documented boundary.
- **Integration boundaries** (WooCommerce, tax, shipping, payment) — the live
  glue is deferred; only framework-free rules exist.
- **Backup / logging risks** — no logging or backup surface exists to assess.

## Test evidence pointer

Signature, replay, dedup, oversell, and transition rules are covered by the 27
PHPUnit tests (`OK (27 tests, 46 assertions)` at commit `83dd05b`). Threats
marked "not executed" above have **no** integration/E2E proof and carry the
stated residual risk.
