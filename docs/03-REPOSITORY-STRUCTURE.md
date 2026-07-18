# Recommended Repository Structure — Resilient WooCommerce Commerce System

```text
resilient-woocommerce-commerce-system/
├── .github/
│   ├── ISSUE_TEMPLATE/
│   ├── pull_request_template.md
│   ├── dependabot.yml
│   └── workflows/
├── apps/                     # only when a separate web/admin/demo app exists
├── packages/                 # reusable domain, UI, contracts, or tooling packages
├── wordpress/
│   ├── themes/
│   ├── plugins/
│   └── mu-plugins/           # only for truly always-on, environment-level concerns
├── tests/
│   ├── unit/
│   ├── integration/
│   ├── contract/
│   ├── e2e/
│   ├── accessibility/
│   ├── performance/
│   └── fixtures/
├── docs/
│   ├── adr/
│   ├── architecture/
│   ├── product/
│   ├── security/
│   ├── accessibility/
│   ├── performance/
│   ├── operations/
│   └── evidence/
├── scripts/
├── playground/               # blueprints and portable demo fixtures
├── reports/                  # generated; generally ignored except curated evidence
├── .editorconfig
├── .gitattributes
├── .gitignore
├── AGENTS.md
├── CHANGELOG.md
├── CODE_OF_CONDUCT.md
├── CONTRIBUTING.md
├── LICENSE
├── README.md
├── SECURITY.md
├── SUPPORT.md
├── composer.json
├── package.json
└── lockfiles governed by ADR
```

## Ownership rules

- Theme: presentation, templates, design tokens, visual styles, and theme-specific patterns.
- Plugin: portable content models, metadata, capabilities, workflows, APIs, migrations, integrations, and functional blocks.
- MU plugin: minimal environment/bootstrap controls that must load before normal plugins; never a dumping ground.
- Frontend app: delivery and interaction, not duplicated canonical business rules.
- Packages: only when there are multiple real consumers or a clear open-source extraction.

## Repository hygiene

- One authoritative command for install, lint, test, build, demo, package, and release.
- Generated files identified and reproducible.
- No committed secrets, local databases, user uploads, vendor builds, or unlicensed assets.
- Exact support policy in README and release notes.
- Small, reviewable commits with meaningful messages.
- Pull requests include problem, approach, risk, test evidence, screenshots for UI, and rollback notes.
