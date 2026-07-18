# Test and Release Plan — Resilient WooCommerce Commerce System

## Test pyramid and contracts

### Static checks

- formatting, linting and coding standards;
- PHP static analysis;
- TypeScript type checking;
- dependency and license review;
- block metadata/schema validation;
- forbidden secret and debug artifact checks.

### Unit tests

Test calculations, state transitions, permissions helpers, serializers, validators, adapter behavior, and error classification without a browser.

### Integration tests

Use a real WordPress database/runtime for hooks, REST routes, migrations, queries, roles/capabilities, content lifecycle, and integration boundaries.

### Contract tests

For every provider or frontend boundary, verify request/response schemas, idempotency, error mapping, timeout, retry, authentication, and version assumptions.

### E2E tests

Use Playwright for the minimum set of high-value user journeys. Avoid reproducing every unit test through the UI. Capture traces, screenshots, console errors, network failures, and accessible names on failure.

### Manual tests

Accessibility, usability, content quality, visual hierarchy, real-device behavior, and operational recovery require human review.

## Compatibility matrix

Document supported and sampled combinations for:

- WordPress current and previous supported lines;
- PHP supported versions;
- WooCommerce where applicable;
- MySQL/MariaDB where meaningful;
- current stable browsers plus required mobile devices;
- multisite if supported;
- clean install, upgrade, deactivate/reactivate, uninstall, and rollback.

Do not imply that untested combinations are supported.

## Release stages

1. local preflight;
2. pull-request targeted checks;
3. sampled matrix;
4. release-candidate full evidence run;
5. artifact inspection from a clean environment;
6. demo/fixture smoke test;
7. tagged release and changelog;
8. post-release health check;
9. rollback drill for high-risk releases.

## Required release artifacts

- versioned package;
- changelog and migration notes;
- test summary and matrix;
- known limitations;
- security-relevant changes;
- accessibility status;
- performance comparison;
- install/upgrade/rollback instructions;
- checksums or provenance information where practical.
