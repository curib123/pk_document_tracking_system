# Modular Red Styling Implementation Plan

> Execute inline with tests before implementation. Existing workflow and request code must remain unchanged.

**Goal:** A red Tailwind design system with truly unstyled per-module fallback.
**Architecture:** PHP config/helper -> safe metadata in shared header -> presentation-only navigation events -> scoped runtime controller and compiled CSS.
**Tech Stack:** CodeIgniter 3/PHP 8, vanilla ES modules, Tailwind CSS 4.1.10, Playwright.
**Spec:** docs/superpowers/specs/2026-10-08-modular-styling.md

## Constraints and review focus
No CDN, no Node required to run deployed PHP, no authorization changes, no schema changes. Test false/string-false config, unknown modules, concurrent navigation, nested dialogs and mobile/reduced-motion behavior.

1. Write failing tests for boolean resolution and native-versus-styled browser behavior.
2. Implement config/helper and presentation-only transport events without changing requests.
3. Build scoped tokens/components/layout/module CSS and a standalone theme controller.
4. Integrate shared header/templates; extend native-browser checks without removing the plain-mode regression.
5. Run local PHP/browser/CSS checks, inspect desktop/mobile screenshots, then run existing GitHub CI before updating master.
