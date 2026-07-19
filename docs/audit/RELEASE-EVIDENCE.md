# Release Evidence

> Canvas §28. Every entry marked *executed* is the real output of a command run
> against this commit. Everything else is in the explicit **Not executed**
> section with its accepted residual risk. This is evidence of real state, not a
> release certification.
>
> **Snapshot note:** this evidence is pinned to the commit below (27 tests / 46
> assertions). The suite has since expanded to **59 tests / 93 assertions** —
> adding tax, shipping, refund-ledger, and webhook→status-resolver rules. See
> `README.md` and `CHANGELOG.md` for the current state; re-run `composer test`
> to reproduce the current figure.

## Identity

| Field | Value |
| --- | --- |
| Plugin | Resilient Commerce |
| Plugin version (header) | `0.1.0` |
| Commit SHA | `83dd05b0138f8b43b7add30fc7f7e5903a7f9efb` |
| Branch | `main` |
| Repository | `https://github.com/pixypuala/resilient-woocommerce-commerce-system` |
| Evidence timestamp (UTC) | 2026-07-18 |
| Build environment | Local: macOS (Darwin), PHP 8.5.8 CLI, Composer v2. CI: GitHub Actions `ubuntu-latest`, `shivammathur/setup-php@v2`, PHP 8.1 / 8.2 / 8.3 / 8.4 |

## Executed — real results at this commit

| Gate | Command | Result |
| --- | --- | --- |
| Composer validation | `composer validate --strict` | `./composer.json is valid` |
| PHP syntax lint | `find . -name '*.php' -not -path './vendor/*' -print0 \| xargs -0 -n1 php -l` | No syntax errors (20 files) |
| Unit tests | `vendor/bin/phpunit` | `OK (27 tests, 46 assertions)` |
| Coding standard | `vendor/bin/phpcs -q --report=summary` | Clean — exit `0`, no violations |
| PHP version (runner) | `php -v` | `PHP 8.5.8 (cli)` |

Verbatim unit-test result:

```
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.5.8
...........................                                       27 / 27 (100%)
OK (27 tests, 46 assertions)
```

**Static analysis / coding standard:** WordPress Coding Standards via
PHP_CodeSniffer (`WordPress-Extra` ruleset, `phpcs.xml.dist`) — clean. No
separate static analyser (PHPStan/Psalm) is configured; this is the full extent
of static checking today.

**PHP matrix (CI):** `.github/workflows/ci.yml` runs composer-validate + install
+ syntax lint + PHPCS + PHPUnit across the full declared `>=8.1` range:
`8.1`, `8.2`, `8.3`, `8.4`. The local runner additionally confirms green on
`8.5.8` (above the declared floor; corroborating only).

## Executed — live WordPress webhook E2E

The webhook inbox has been exercised **end-to-end against a real, booted
WordPress + MySQL** (WordPress running via `wp-env` / Docker, the plugin active,
the signing secret set through `RESILIENT_COMMERCE_WEBHOOK_SECRET`). The harness
(`tests/e2e/webhook-e2e.sh`, CI: `.github/workflows/webhook-e2e.yml`) POSTs real
HMAC-signed requests to `POST /wp-json/resilient-commerce/v1/webhook` and asserts
the status/`result` for each path. Real output:

```
Endpoint: http://localhost:8890/wp-json/resilient-commerce/v1/webhook
  PASS  valid delivery accepted            (HTTP 200, result=accepted)
  PASS  redelivery is idempotent duplicate (HTTP 200, result=duplicate)
  PASS  bad signature rejected             (HTTP 401, result=invalid_signature)
  PASS  stale timestamp rejected           (HTTP 408, result=stale)
E2E result: 4 passed, 0 failed
```

Corroborated via WP-CLI against the live database: the activation hook created
the custom table (`SHOW TABLES LIKE '%rc_processed_events%'` → `wp_rc_processed_events`),
and the dedup ledger persisted the processed event ids
(`SELECT COUNT(*) FROM wp_rc_processed_events` → non-zero). So the **atomic
`$wpdb` UNIQUE-key dedup is DB-proven, not only design-proven**, and activation +
custom-table creation are verified on a real WordPress.

## Not executed — documented boundaries and accepted residual risks

These canvas §28 evidence items were **not** produced. They are not claimed as
passed. Each carries the accepted residual risk of shipping a v0.1.0 core.

| Evidence item | Status | Accepted residual risk |
| --- | --- | --- |
| WordPress-version compatibility matrix | Not executed | `Requires at least: 6.5` is declared, not tested against real WP builds. Behaviour on specific WP versions unproven. |
| PHP matrix beyond CI (real runtime, not just lint/standards) | Partial | CI proves lint + standards + unit tests on 8.1–8.4; no *WordPress-loaded* runtime run on each. |
| Integration tests (hooks / REST / `$wpdb` / activation) | **Executed (webhook path)** | REST controller, `$wpdb` dedup store, and activation table creation are now exercised against a booted WordPress (see *Executed — live WordPress webhook E2E* above). The WooCommerce order-sync path against a live `WC_Order` is still not exercised. |
| End-to-end (webhook over HTTP) | **Executed** | Signed-webhook, replay-window, and dedup behaviour validated over real HTTP against a live site. The browser **checkout** journey (Playwright) is still not run. |
| Multisite | Not executed | Network activation and cross-site isolation unverified. |
| Performance / load | Not executed | No query-count, latency, memory, or throughput budgets measured. |
| Accessibility | Not executed (mostly N/A) | Plugin has no UI to audit; any future UI is unassessed. |
| Security tests (injection / CSRF / authz / SSRF suites) | Not executed | Security rests on code review + the threat model + unit tests, not an automated security suite. |
| Migration / upgrade / rollback tests | Not executed | `CREATE TABLE IF NOT EXISTS` only; no versioned migrations, no rollback runbook. |
| Uninstall behaviour | Not executed / not implemented | No `uninstall.php`; dedup table retention on uninstall is undefined. |
| Plugin Check | Not executed | WordPress.org Plugin Check not run. |
| Dependency audit / secret scanning | Not executed | No automated supply-chain scan in CI (runtime deps limited to `ext-json`; `composer.lock` committed). |
| Package artifact + checksum | Not executed | No distributable `.zip` / `checksums.txt` built for this commit. |
| Final reviewer approval | Not applicable | This is portfolio evidence of real state, not a signed release gate. |

## Known limitations

- Revenue-critical **rules** are implemented and unit-tested; the **live
  WooCommerce binding** (real `WC_Order`, refunds, tax, shipping) is a
  documented boundary and is not part of this evidence.
- No admin UI, observability, diagnostics, rate limiting, or data-retention job.

## Rollback artifact

None produced. There is no versioned migration and no packaged release, so there
is no rollback package to attach. Documented boundary.

## Reproducibility

From a fresh clone at `83dd05b`:

```bash
composer install
composer test    # → OK (27 tests, 46 assertions)
composer lint    # → PHPCS clean, exit 0
```

The results in this document reproduce from that sequence on PHP 8.1–8.5.
