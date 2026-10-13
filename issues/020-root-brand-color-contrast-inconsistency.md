# Issue 020: Root brand color levels are inconsistent and can reduce text contrast

Severity: MEDIUM (UI/accessibility) | Status: Confirmed root-palette inconsistency | Implemented: NO

Affected: public/assets/css/colors.css.

The current brand levels are not ordered from dark to light: brand-950 is #e60d38, brand-900 is #ba142d, brand-800 is #cc0a2b and brand-700 is #f6082f. The bright brand-700 has a computed contrast ratio of approximately **4.20:1 on white**, below WCAG AA's 4.5:1 requirement for normal text. A centralized CSS palette exists but its scale is internally inconsistent.

## Copyable fix — replace only these root token values

In the existing :root block of colors.css, replace the current seven brand-level definitions with:

~~~css
--ui-brand-950: #711729;
--ui-brand-900: #881b2d;
--ui-brand-800: #9f2034;
--ui-brand-700: #b4273b;
--ui-brand-600: #c82b43;
--ui-brand-500: #d4263a;
--ui-brand-400: #df596b;
~~~

Leave brand-300/200/150/100/50 as the existing light-tint scale until visual review. With this proposed palette, brand-700 on white has a computed contrast of about 6.38:1 and brand-500 about 5.08:1. Brand-400 is lighter and should be for decorations/background accents, NOT normal white-background body text.

## Verify
Check Buttons, sidebar, alerts, dashboard cards, active nav/tab states, folder tree, modals, backup screen and mobile hover/focus states. Use browser accessibility tools to verify contrast against actual background colors, including dark surfaces and disabled controls. Run existing PHP and UI tests. Do not assume that merely using centralized tokens guarantees WCAG AA for every combination.
