# Baseline Audit

> Canvas §5.2 / §5.3. This records the **real, reproducible** state of the
> repository at the commit below. Every result is the verbatim output of a
> command run in this repo. Nothing here is aspirational.

## Environment

| Field | Value |
| --- | --- |
| Branch | `main` |
| Commit | `83dd05b0138f8b43b7add30fc7f7e5903a7f9efb` |
| Plugin version (header of `resilient-commerce.php`) | `0.1.0` |
| PHP (local audit runner) | `PHP 8.5.8 (cli)` (Homebrew, NTS) |
| Composer | v2 |
| PHPUnit | `10.5.64` |
| PHP_CodeSniffer | `3.10` + `wp-coding-standards/wpcs 3.1` |
| Declared PHP support | `>=8.1` (composer.json), `Requires PHP: 8.1` (header) |
| Declared WP support | `Requires at least: 6.5` |

> Note: the audit runner used PHP 8.5.8, which is **above** the declared
> `>=8.1` floor. CI (`.github/workflows/ci.yml`) exercises the declared range
> on 8.1 / 8.2 / 8.3 / 8.4. PHP 8.5 is not a declared support target; its green
> result here is corroborating, not a compatibility claim.

## Baseline commands and real results

The canvas §5.2 command list is adapted to what this repository actually
defines (a framework-free, unit-tested domain core — no JS build, no live
WordPress install in this workspace).

| Command | Result |
| --- | --- |
| `php -v` | `PHP 8.5.8 (cli)` |
| `composer validate --strict` | `./composer.json is valid` |
| `find . -name '*.php' -not -path './vendor/*' -print0 \| xargs -0 -n1 php -l` | No syntax errors across all 20 non-vendor PHP files |
| `vendor/bin/phpunit` | `OK (27 tests, 46 assertions)` |
| `vendor/bin/phpcs -q --report=summary` | Clean — exit code `0`, no reported violations |
| `git rev-parse HEAD` | `83dd05b0138f8b43b7add30fc7f7e5903a7f9efb` |
| `git rev-parse --abbrev-ref HEAD` | `main` |

Verbatim PHPUnit tail:

```
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.5.8
Configuration: .../phpunit.xml.dist
...........................                                       27 / 27 (100%)
Time: 00:00.029, Memory: 8.00 MB
OK (27 tests, 46 assertions)
```

Commands **not** run in this workspace and why (canvas §5.2 lists them for
plugins that define them; this one does not):

- The Node/JS build and E2E scripts listed by the canvas (`install`, `build`,
  `lint`, `test`, `test:e2e`) — no `package.json`, no JS build or E2E harness
  exists in the repo. Documented boundary.
- `wp plugin activate|deactivate`, `wp cron event list`, `wp option get
  active_plugins` — no live WordPress runtime is provisioned in this workspace.
  Activation is wired (`register_activation_hook` → `WpdbEventStore::install()`)
  but has not been exercised against a real `$wpdb`. Documented boundary.
- `composer phpstan` — no `phpstan.neon` / static-analysis stage exists. Static
  analysis today is limited to PHP syntax lint + WordPress coding standards.

## Repository discovery inventory (§5.1)

The plugin bootstrap is `resilient-commerce.php` (valid plugin header, direct
enqueue of heavy logic avoided; the REST route registers on `rest_api_init`
only when a signing secret constant is defined — fail-safe).

### What actually exists in `src/` (845 lines total)

| Component | File(s) | Responsibility |
| --- | --- | --- |
| Webhook inbox | `src/Webhook/WebhookInbox.php` | Orders the security gauntlet: signature → well-formedness → replay window → atomic dedup → run handler exactly once. Returns an `InboxResult`. |
| Signature verifier | `src/Webhook/SignatureVerifier.php` | Constant-time HMAC (`hash_equals`) over the raw body; rejects empty secret / unknown algorithm at construction; tolerates `algo=` header prefixes. |
| Processed-event store (contract) | `src/Webhook/ProcessedEventStore.php` | Interface: `has()` / `claim()`. |
| `$wpdb` event store | `src/Webhook/WpdbEventStore.php` | Persistent dedup via a `UNIQUE` key on `event_id`; `claim()` is a single `INSERT` so the **database** arbitrates concurrent deliveries. `install()` creates `{prefix}rc_processed_events`. Prepared statements for value binding. |
| In-memory event store | `src/Webhook/InMemoryEventStore.php` | Test/double implementation of the store contract. |
| Inbox result enum | `src/Webhook/InboxResult.php` | `Accepted` / `Duplicate` / `InvalidSignature` / `Stale` / `Malformed`. |
| REST controller | `src/Http/WebhookController.php` | Thin WordPress adapter: extracts raw body + headers, calls the inbox, maps `InboxResult` → HTTP status, fires `resilient_commerce_webhook`. `permission_callback` = `__return_true` (HMAC is the authenticator). |
| Stock ledger | `src/Inventory/StockLedger.php` | Oversell-safe reservations: reserved vs committed, TTL reclaim via injected clock, availability never below zero. |
| Order state machine | `src/Order/OrderStateMachine.php`, `OrderStatus.php` | WooCommerce-aligned lifecycle; idempotent no-op on redelivery; illegal transitions rejected loudly. |
| Domain errors | `src/Inventory/StockException.php`, `src/Order/OrderException.php` | Typed, developer-facing exceptions. |
| Clock | `src/Clock/Clock.php`, `SystemClock.php` | Injectable time source keeping domain logic pure/testable. |

### Storage, hooks, REST, cron

- **Custom table:** `{wpdb->prefix}rc_processed_events` — columns `id`
  (PK, auto-increment), `event_id` (`VARCHAR(191)`, `UNIQUE`), `processed_at`.
- **REST route:** `POST /resilient-commerce/v1/webhook`.
- **Action fired:** `resilient_commerce_webhook( string $body, string $event_id )`
  — the documented extension point for order/refund side effects.
- **Activation hook:** creates the dedup table.
- **Cron jobs:** none defined.
- **Options / transients / user-meta / uploads / external HTTP requests:** none.

### Tests present (`tests/`)

`OrderStateMachineTest`, `SignatureVerifierTest`, `StockLedgerTest`,
`WebhookInboxTest`, plus `tests/Support/MutableClock.php`. 27 tests / 46
assertions, all green.

## Known gaps (honest)

- No WordPress **integration** tests (hooks/REST/activation exercised against a
  real `$wpdb` or `WP_REST_Request`). The `$wpdb` store and REST controller are
  covered only by their framework-free collaborators, not a booted WordPress.
- No **E2E** (browser/Playwright), **multisite**, **accessibility**,
  **performance/load**, or **WP-version** compatibility testing.
- No **static analyser** (PHPStan/Psalm) stage.
- No live **WooCommerce** binding: the state machine defines the *rules* but is
  not wired to real `WC_Order` objects, refunds, tax, or shipping.
- No **uninstall** routine (`uninstall.php`) — the dedup table is not removed on
  uninstall; data-retention policy for it is undocumented.
- No **Site Health**, diagnostics, repair tools, or structured audit logging.

## Missing tests

- Integration coverage for `WpdbEventStore` against a real MySQL/`$wpdb`
  (the `UNIQUE`-key race is proven by design + in-memory analogue, not by a DB).
- REST-layer tests for anonymous / malformed / valid requests through
  `WebhookController::handle()`.
- Activation / uninstall lifecycle tests.

## Missing documentation (relative to canvas §23)

Present: `README.md`, `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`,
`CODE_OF_CONDUCT.md`, `SUPPORT.md`, `LICENSE`, and a `docs/` design set.
Not yet present: `readme.txt`, architecture/data-model/security-model docs,
operations runbooks (upgrades/rollback/backup/troubleshooting), privacy docs.
This audit adds `docs/audit/*` and `docs/security/THREAT-MODEL.md`.

## Existing technical debt / production risks

- The plugin **fails safe** without `RESILIENT_COMMERCE_WEBHOOK_SECRET`
  (no endpoint registered) — correct, but there is no admin surface or Site
  Health notice telling an operator the endpoint is dormant.
- No rate limiting on the public webhook route beyond HMAC rejection; a flood of
  invalid-signature requests is only cheaply rejected, not throttled.
- `processed_events` grows unbounded (no pruning of old ids). Low risk at
  expected volume, but no retention job exists.

None of the above are concealed regressions; they are the honest starting state
of a v0.1.0 tested core.
