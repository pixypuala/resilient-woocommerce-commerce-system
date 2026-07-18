# Resilient WooCommerce Commerce System

## Portfolio purpose

A revenue-critical WooCommerce reference implementation that proves commerce architecture, integration safety, performance, observability, and operational judgment.

This project is not considered complete when the UI looks good. It must demonstrate discovery, architecture, code quality, accessibility, security, performance, test design, deployment, recovery, documentation, and public communication.

## Getting started

Requires PHP 8.1+ and Composer.

```bash
composer install
composer test    # 27 unit tests: signature verification, replay-safe inbox, oversell-safe stock, order-state rules
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

## Documented boundary (not yet built)

The thin WooCommerce glue that maps the state machine onto live `WC_Order` objects, refund
handlers, tax/shipping adapters, the operations console, Playwright checkout journeys, and the
WooCommerce contract-test extraction (`wc-integration-contract-test-kit`). The state-transition
*rules* are built and tested (above); only the WooCommerce-loaded binding is deferred, because it
cannot be exercised without a full WooCommerce runtime.

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
