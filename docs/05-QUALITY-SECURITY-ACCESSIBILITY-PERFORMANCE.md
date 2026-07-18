# Quality Gates — Resilient WooCommerce Commerce System

## Code correctness

- PHP syntax and static analysis pass at the documented level.
- WordPress coding standards are enforced with reviewed exceptions.
- TypeScript strict mode passes; no unexplained `any`, ignored errors, or disabled rules.
- Domain rules have unit tests; infrastructure behavior has integration/contract tests.
- Migrations are idempotent or explicitly one-way with safe detection.
- Time, randomness, external calls, and environment state are controllable in tests.

## WordPress security

- Validate or reject input as early as practical.
- Sanitize input when normalization is required.
- Escape output as late as possible for its exact context.
- Use capabilities for authorization; nonces help protect intent but do not grant permission.
- Parameterize database queries and avoid direct writes that bypass platform invariants.
- Protect REST routes and server actions independently of UI visibility.
- Restrict uploads, paths, URLs, MIME types, and external requests.
- Do not expose internal exceptions, secrets, personal data, or privileged object IDs.
- Review uninstall behavior, retention, privacy export/erase, and multisite implications.

## Accessibility

Target WCAG 2.2 Level AA as an implementation goal; claim conformance only after the defined scope is completely tested.

Manual checks include:

- keyboard-only completion of critical tasks;
- visible focus and focus not obscured;
- logical heading, landmark, label, name/role/value, and error association;
- 200% and 400% zoom/reflow where applicable;
- reduced motion and no essential motion-only information;
- target size and dragging alternatives;
- screen-reader walkthrough of at least the critical workflow;
- forced colors/high contrast where supported;
- editor experience as well as frontend experience for WordPress projects.

## Performance

- Define budgets by journey and device/network profile.
- Measure LCP, INP, and CLS, but also inspect server response, queries, cache hit/miss behavior, JavaScript execution, asset weight, images, fonts, and third-party cost.
- Use the 75th percentile for field Core Web Vitals interpretation when enough valid field data exists.
- Keep lab and field data distinct.
- Prevent global loading of feature-specific assets.
- Add performance regression checks with tolerances that avoid noisy failures.

## Release gate

A release fails if critical security, data-loss, checkout/publishing, accessibility blocker, migration, or rollback tests fail. A green build is necessary but not sufficient; the release checklist requires human review of risk-specific evidence.
