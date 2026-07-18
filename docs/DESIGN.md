# Design Foundation — Resilient WooCommerce Commerce System

- **Discipline (all aspects):** [`09-TEMPLATES/DESIGN-SYSTEM-CLAUDE.md`](../09-TEMPLATES/DESIGN-SYSTEM-CLAUDE.md) — surface pacing, scarce accent, 6-step type scale with negative tracking, editorial whitespace, hairline-over-shadow elevation.
- **Palette + fonts (override):** [`09-TEMPLATES/DESIGN-TOKENS-WORDPRESS.md`](../09-TEMPLATES/DESIGN-TOKENS-WORDPRESS.md) — wordpress.com landing palette (Blueberry `#3858E9` on white) + WordPress system font stack.

Storefront, product, cart, and checkout inherit the tokens. Reserve blue (`primary`) for the single primary action per view (Add to cart, Place order); dark surfaces for order summaries/receipts. Error/success use semantic tokens (#D63638 / #00A32A), never ad-hoc colors.

## Required accessibility extensions

The tokens are the visual language only. This project MUST meet WCAG 2.2 AA:

- Interactive targets ≥ **44×44px** (raise via padding).
- Every interactive element has a **visible, theme-aware focus ring** (2px offset, `--focus-ring`).
- Honor **prefers-reduced-motion** and **prefers-contrast**.
- Verify blue-on-white, blue-on-blue, and muted pairings hit AA; darken where they fail.
- All color via CSS variables / tokens — never inline hex.

## Rule

White canvas + one WordPress blue (`#3858E9`) + one dark product surface — no cream, no coral, no fourth tone. **Discipline** from Anthropic, **palette + type** from wordpress.com (Recoleta/Fraunces serif display + system-stack body + Space Mono, not Copernicus/StyreneB).
