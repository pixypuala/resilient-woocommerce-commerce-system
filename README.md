# Resilient WooCommerce Commerce System

## Portfolio purpose

A revenue-critical WooCommerce reference implementation that proves commerce architecture, integration safety, performance, observability, and operational judgment.

This project is not considered complete when the UI looks good. It must demonstrate discovery, architecture, code quality, accessibility, security, performance, test design, deployment, recovery, documentation, and public communication.

## Getting started

Requires PHP 8.1+ and Composer.

```bash
composer install
composer test    # 59 unit tests: signature verification, replay-safe inbox, oversell-safe stock, order-state rules, tax, shipping, refunds, webhook status mapping
composer lint    # WordPress coding standards (PHPCS)
```

## What is built today

The revenue-critical core is implemented and unit-tested without WordPress:

- **Replay-safe webhook inbox** (`src/Webhook/`) — every delivery must pass, in order:
  constant-time HMAC **signature** verification → well-formedness → **replay window** →
  atomic **deduplication**. Only then does the handler run, exactly once. This is what
  prevents duplicate refunds and double-fulfilled orders. A `$wpdb`-backed store makes the
  dedup claim atomic via a `UNIQUE` key, so concurrent deliveries cannot both win.
- **Oversell-safe stock ledger** (`src/Inventory/`) — separates reserved (a checkout hold
  with TTL) from committed stock; availability always accounts for live reservations, so a
  reservation can never drive stock below zero. Abandoned holds are reclaimed on expiry.
- **REST integration** (`src/Http/`, `resilient-commerce.php`) — a `POST /resilient-commerce/v1/webhook`
  route wires the inbox to WordPress and fails safe (no endpoint unless a signing secret is set).
- **Idempotent order-state rules** (`src/Order/`) — a WooCommerce-aligned `OrderStateMachine` that
  applies status changes from order webhooks: a redelivered event reporting the current status is a
  safe no-op, and any transition the lifecycle forbids (e.g. `completed → processing`) is rejected
  loudly instead of corrupting order state. Framework-free and unit-tested without WooCommerce, so it
  can be driven behind the `resilient_commerce_webhook` action.
- **Deterministic tax calculator** (`src/Tax/`) — computes tax over net line items for one or more
  rates using integer minor units and integer `rate_e4` rates, so the only fractional step is a single
  explicit half-up rounding per rate. Handles zero rates and compound ("tax on tax") rates, with an
  auditable per-rate breakdown. No float arithmetic, so results are reproducible.
- **Shipping-rate selector** (`src/Shipping/`) — picks the cheapest eligible rate for a cart's
  subtotal and weight. Free-shipping thresholds and per-weight tiers are encoded on each rate; ties are
  broken deterministically and an unservable cart fails loudly rather than silently offering nothing.
- **Partial-refund rules** (`src/Order/Refund.php`, `RefundLedger.php`) — a `Refund` value object plus
  a ledger that tracks the captured total and cumulative refunds and enforces the absolute invariant:
  you can never refund more than was captured. Over-refunds are rejected before any money moves; a full
  refund drives the `Refunded` status.
- **WooCommerce order-sync adapter** (`src/Integration/`, `src/Order/WebhookStatusResolver.php`) — the
  payload→status decision logic is extracted into a framework-free, unit-tested resolver; the adapter
  subscribes to `resilient_commerce_webhook`, is dependency-detected via `function_exists( 'wc_get_order' )`,
  and applies the resolved change through the tested `OrderStateMachine`. Only the `wc_get_order` load
  and `save()` are live-WooCommerce glue.

## Documented boundary (not yet built)

What is deferred is the code that can only run inside a full WooCommerce runtime or a real browser:
executing the order-sync adapter against live `WC_Order` objects (loading, `set_status`, `save`), the
tax/shipping/refund *bindings* onto WooCommerce carts and refund objects, the operations console UI,
Playwright checkout journeys, and the WooCommerce contract-test extraction
(`wc-integration-contract-test-kit`). The revenue-critical *rules* — tax, shipping, refunds, order-state
transitions, and the webhook→status mapping — are built and unit-tested above; only their
WooCommerce-loaded and browser-driven bindings remain, because they cannot be exercised without those
runtimes.

## Audit & security evidence

Real, reproducible evidence of the current state (all figures come from commands
run against the repo, not aspirations):

- [`docs/audit/BASELINE-AUDIT.md`](docs/audit/BASELINE-AUDIT.md) — branch, commit, install/test/standards results, `src/` inventory, and honest gaps.
- [`docs/security/THREAT-MODEL.md`](docs/security/THREAT-MODEL.md) — assets, trust boundaries, the public webhook attack surface, and a grounded threat table.
- [`docs/audit/FINAL-AUDIT.md`](docs/audit/FINAL-AUDIT.md) — §26 walkthrough, the §16.5 quality scorecard, and the honest maturity classification.
- [`docs/audit/RELEASE-EVIDENCE.md`](docs/audit/RELEASE-EVIDENCE.md) — version, SHA, PHP/test/standards results, and the explicit "not executed" boundaries.

## PCAAP

### Problem

A storefront can look polished while checkout, inventory, taxes, shipping, webhooks, background jobs, and third-party failures remain brittle.

### Cost

Lost orders, duplicated payments, inventory mismatches, inaccessible checkout, support burden, poor mobile performance, and uncertain incident recovery.

### Answer

Build a realistic store with a custom block theme and focused commerce plugin. Implement idempotent integration boundaries, webhook verification, order-state rules, stock reservations, operational dashboards, synthetic checkout tests, and recovery runbooks.

### Advantage

The portfolio demonstrates the systems behind revenue rather than only product-grid styling. Failure scenarios are designed and tested, not hidden.

### Proof required

- repeatable test catalog and seeded orders
- checkout E2E across guest/account/coupon/shipping cases
- signed webhook and replay tests
- idempotency and duplicate-event tests
- load and query profile on catalog/cart/checkout
- accessibility review of complete purchase journey
- observability screenshots with synthetic data
- backup restore and order reconciliation drill

### Ask

Run the failure suite, inspect the order-state invariants, and review the incident/rollback evidence for commerce or full-stack roles.

## Intended audience

growing retailer, subscription brand, B2B seller, marketplace operator, WooCommerce agency.

## Core stack and capabilities

- WordPress and WooCommerce current supported versions in a matrix
- PHP service layer and WooCommerce extension APIs
- Store API and block-based checkout where appropriate
- TypeScript admin/operations interfaces
- MySQL query/index analysis
- queue/background job abstraction with deterministic local runner
- payment/shipping/tax sandbox adapters only
- Playwright, PHPUnit and contract tests
- structured logs, health checks and synthetic monitoring
- containerized local environment and CI fixtures

## Product scope

- catalog with variable products and realistic inventory
- cart and checkout with validation and recoverable errors
- tax/shipping adapter interfaces and test doubles
- payment intent/order reconciliation using sandbox data
- webhook inbox with signature, deduplication and replay safety
- stock reservation and oversell prevention tests
- refund, cancellation, partial fulfillment and failed-payment workflows
- operations console for stuck jobs and reconciliation
- privacy-safe analytics and Core Web Vitals collection
- incident timeline and support-ready order diagnostics

## Major risks

- handling real card data or claiming PCI certification
- inventing conversion improvements
- testing only the happy path
- direct database writes that bypass WooCommerce invariants
- unbounded logs containing personal data
- using production credentials in a public demo

## Milestone order

1. commerce domain model and threat model
2. storefront and accessible product discovery
3. cart/checkout baseline
4. provider adapter contracts and sandbox integration
5. webhook/reconciliation system
6. operations and observability
7. failure, recovery and performance testing
8. case study and reusable adapter extraction

## Public repository opportunity

Extract the generally useful portion as `wc-integration-contract-test-kit`. The public repository must have an open-source license, contribution guide, security policy, support boundary, reproducible local setup, release notes, and a roadmap that distinguishes committed work from ideas.

## Definition of portfolio-ready

- a stranger can run the project from a fresh clone;
- every major claim links to a test, report, trace, screenshot, or explicit limitation;
- no production credentials, personal data, copied proprietary code, or fake testimonials exist;
- repository issues reflect honest known gaps;
- the demo includes at least one controlled failure and recovery;
- architecture decisions explain alternatives and tradeoffs;
- the case study can be understood by both technical and nontechnical readers;
- the latest tagged release passes the documented support matrix.
