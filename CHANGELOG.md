# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Live webhook E2E: `.wp-env.json` + `tests/e2e/webhook-e2e.sh` + `.github/workflows/webhook-e2e.yml` boot a real WordPress (Docker) with the plugin active and drive the REST webhook endpoint over HTTP, asserting accepted/duplicate/invalid-signature/stale live. Verified the activation hook creates `wp_rc_processed_events` and the atomic `$wpdb` dedup ledger persists ids (DB-proven idempotency). Evidence in `docs/audit/RELEASE-EVIDENCE.md`.
- Repository scaffolding: governance files, docs, and CI skeleton.
- Replay-safe, idempotent webhook inbox: constant-time HMAC verification, replay window, atomic dedup ($wpdb UNIQUE-key store).
- Oversell-safe stock ledger: reserved vs committed stock, TTL reclaim, never sells below zero.
- REST webhook endpoint wiring (fails safe without a signing secret).
- Idempotent, WooCommerce-aligned `OrderStateMachine` (`src/Order/`): redelivered status events are safe no-ops; illegal lifecycle transitions are rejected.
- Deterministic, framework-free tax calculator (`src/Tax/`): integer minor units and integer `rate_e4` rates, half-up rounding, zero-rate and compound ("tax on tax") support, per-rate breakdown.
- Framework-free shipping-rate selector (`src/Shipping/`): free-shipping thresholds and per-weight tiers on each rate; picks the cheapest eligible rate deterministically; fails loudly when none applies.
- Partial-refund rules (`src/Order/Refund.php`, `RefundLedger.php`): a `Refund` value object and a ledger enforcing that cumulative refunds never exceed the captured amount; a full refund drives the `Refunded` status.
- WooCommerce order-sync adapter (`src/Integration/WooCommerceOrderSync.php`) with an extracted, unit-tested `WebhookStatusResolver` (`src/Order/`): subscribes to `resilient_commerce_webhook`, is dependency-detected via `function_exists( 'wc_get_order' )`, and applies changes through the `OrderStateMachine`; only `wc_get_order`/`save()` are live-WooCommerce glue.
- 59 PHPUnit tests; PHPCS/WPCS clean; CI on PHP 8.1, 8.2, 8.3, and 8.4.
- WordPress-Proof canvas evidence set: `docs/audit/BASELINE-AUDIT.md`, `docs/security/THREAT-MODEL.md`, `docs/audit/FINAL-AUDIT.md`, `docs/audit/RELEASE-EVIDENCE.md`.

### Changed
- Widened the CI PHP matrix from `8.1, 8.3` to the full declared range `8.1, 8.2, 8.3, 8.4`.
