# Modular red styling

## Intent
Add an opt-in/opt-out design layer to the existing PK DTS without rewriting its document, request, permission or approval behavior. Administrator, Document Control Officer and staff use the same interface. The user selected a modern, clean red Tailwind SaaS style.

## Design
One PHP configuration is authoritative: global enabled boolean, shell boolean, unknown-module default false, explicit module booleans. All existing modules start enabled. A false module is rendered with native HTML controls and no application CSS on that screen; its business behavior is unchanged. A global false is an emergency unstyled switch. Legacy database appearance JSON is not a second source of truth for these switches.

Compile Tailwind at development time, commit static CSS, do not use a CDN or require Node on the XAMPP server. No Preflight/global reset: every visual rule is scoped to a styled root or an explicit enabled-shell attribute. Central tokens, shared components, layout, and small module extensions are separate files. New unregistered modules remain plain until deliberately enabled.

Reuse existing semantic templates and native dialogs. The shared API transport emits presentation-only screen events; these never influence endpoints, data, permissions, or workflow decisions. A separate theme controller applies scopes, tracks navigation, styles dynamically created dialogs, and manages a responsive menu. The visual shell uses a white sidebar, muted canvas, red active states, compact tables, readable labels, clear destructive actions, keyboard focus, reduced-motion support and responsive dialogs.

## Verification
PHP boolean/override tests; browser comparison against native computed styles; enabled/disabled navigation in both directions; dialog isolation; mobile overflow and keyboard menu handling; static CSS rebuilt without global selectors; existing functional CI remains intact. No database schema changes.
