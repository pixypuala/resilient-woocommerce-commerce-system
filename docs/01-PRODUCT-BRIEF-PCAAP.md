# Product Brief — Resilient WooCommerce Commerce System

## Outcome

Create a public, reproducible reference project that demonstrates the ability to solve a real growing retailer, subscription brand, B2B seller, marketplace operator, WooCommerce agency problem from discovery through maintenance.

## Problem and cost

**Problem:** A storefront can look polished while checkout, inventory, taxes, shipping, webhooks, background jobs, and third-party failures remain brittle.

**Cost:** Lost orders, duplicated payments, inventory mismatches, inaccessible checkout, support burden, poor mobile performance, and uncertain incident recovery.

## Users and jobs to be done

1. **Primary operator:** completes the central workflow without developer assistance.
2. **Administrator:** configures permissions, integrations, and policy safely.
3. **Developer/maintainer:** updates the system, diagnoses failures, and extends it through documented contracts.
4. **Reviewer/auditor:** verifies security, accessibility, performance, and release evidence.
5. **Recruiter/client:** understands the outcome and the developer's contribution without reading every source file.

## Functional scope

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

## Explicit non-goals for the first release

- no fake production scale or fabricated customers;
- no paid-vendor dependency required to run the core demo;
- no feature that exists only for a resume keyword;
- no hidden setup steps performed manually by the author;
- no broad compliance certification claim;
- no unsupported browser, PHP, WordPress, or provider promise.

## Acceptance outcomes

- The central workflow is documented as Given/When/Then scenarios.
- Every destructive action has authorization, confirmation, auditability where appropriate, and recovery documentation.
- Empty, loading, error, permission-denied, offline/unavailable, and stale-data states are designed.
- Accessibility is tested by keyboard and at least one screen-reader workflow, plus automation.
- Performance budgets are tied to user journeys, not a homepage-only score.
- CI produces useful artifacts when a test fails.
- A tagged release can be installed from a clean environment.
- The case study distinguishes measured results, fixture results, estimates, and unvalidated hypotheses.

## Success measures

Use measurements that the project can truthfully collect:

- task completion and error rate in a small documented usability test;
- regression count detected before release;
- build/test duration and flake rate;
- Core Web Vitals or controlled-lab journey metrics with environment stated;
- accessibility issues by severity and resolution status;
- query count/time for defined requests;
- recovery time during a scripted failure drill;
- external repository clones, issues, pull requests, or stars only as descriptive adoption data, never as quality proof.

## Ask

Run the failure suite, inspect the order-state invariants, and review the incident/rollback evidence for commerce or full-stack roles.
