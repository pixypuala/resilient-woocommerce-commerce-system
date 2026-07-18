# Architecture and ADR Plan — Resilient WooCommerce Commerce System

## Architecture principles

- Separate domain rules, presentation, infrastructure, and provider integrations.
- Prefer WordPress/browser/platform APIs over custom infrastructure where they meet the requirement.
- Keep data ownership explicit.
- Reject invalid state at boundaries and preserve useful error codes.
- Make permission checks server authoritative.
- Keep optional integrations replaceable.
- Design migrations before storing long-lived data.
- Observe critical flows without logging secrets or unnecessary personal data.
- Make local and CI behavior equivalent enough to trust evidence.

## Required ADRs

1. repository and package boundaries;
2. theme versus plugin versus application ownership;
3. build tooling and package-manager authority;
4. data model and migration strategy;
5. rendering and interactivity approach;
6. authentication and authorization boundary;
7. integration adapter design;
8. caching and invalidation;
9. background processing and retry behavior;
10. observability and privacy;
11. browser/E2E test boundary;
12. support matrix and deprecation policy;
13. release, rollback, and emergency-fix process;
14. optional AI usage, data handling, permissions, evaluation, and fallback;
15. open-source licensing and third-party asset policy.

## Data-flow documentation

For each critical workflow, provide:

- user/actor;
- input and validation;
- server-side capability/permission decision;
- state transition;
- external calls and timeout/retry rules;
- persisted data and retention;
- emitted events/logs;
- response and accessible feedback;
- rollback or reconciliation path.

## Threat-model minimum

Cover unauthorized access, CSRF, stored/reflected XSS, SQL injection, SSRF where URLs are accepted, file upload abuse, insecure direct object reference, privilege escalation, replay/duplicate events, secret exposure, dependency compromise, denial of service, privacy leakage, unsafe logs, and destructive test execution.

## Architecture review questions

- Can a theme change without losing durable content or workflow?
- Can a provider be replaced without rewriting domain logic?
- Can a stale or duplicated request corrupt state?
- Can the user recover from a partial failure?
- Are all state transitions valid and tested?
- Does the browser receive only the data it needs?
- Can a contributor understand the system without private context?
- What is intentionally simple, and what evidence would justify more complexity?
