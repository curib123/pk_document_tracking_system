# Modular styling — red design system

## Turn styling on or off

Edit `application/config/styling.php`. This remains the authoritative styling configuration. This upgrade does not change the module flags.

```php
'enabled' => true,           // false: the app uses plain HTML
'shell' => true,             // sidebar/header design on enabled screens
'default_enabled' => false,  // example policy for unregistered modules
'modules' => [
    'softcopy' => true,
    'hardcopy' => false,
    'workflows' => true,
    // Keep the remaining existing module entries.
],
```

Use actual PHP booleans, not quoted strings. Reload after changing the configuration. A false module keeps native form/table styling and does not receive the Poppins font-family override. Other modules retain their settings. Dialogs inherit their originating module; account dialogs use `account`.

`PK_STYLING_ENABLED=0` disables styling globally for troubleshooting. On a page reload, the global false flag also prevents loading the external font/icon links. This is a server setting, not an authorization permission or public query parameter. No database migration is required.

## HTML belongs in views

Page HTML lives in `application/views/pages/<module>/` and reusable markup in `application/views/components/`. JavaScript clones the PHP-owned templates and binds values/events. Do not introduce HTML strings, SVG path maps, or template construction in JavaScript. The existing view architecture and request workflows are unchanged.

## Tailwind CSS 4.3

The compiler is pinned to **Tailwind CSS 4.3.1**, including `package-lock.json`. Both `public/assets/css/app.css` and `public/assets/css/workspace.css` are compiled and committed. XAMPP needs no Node server or browser Tailwind compiler.

Rebuild with Node 22 and npm:

```sh
npm ci
npm run build:css
npm run check:css
npm run test:assets
```

`build:css` and `check:css` cover both shared components and the reference workspace. The semantic component build uses Tailwind's CSS compiler and `@apply`, without global Preflight resets. Keep selectors scoped so disabled modules remain plain. Commit CSS source, lockfile changes and regenerated CSS together.

## Poppins and Font Awesome

The shared head partial `application/views/components/assets/styles.php` includes:

- Font Awesome **6.7.2**, using the requested cdnjs `all.min.css` URL.
- Google Fonts **Poppins**, weights **400, 500, 600, 700**, with `display=swap`.
- The Google Fonts and gstatic preconnect links, with crossorigin on gstatic.

The main layout requires the partial once. `--pk-font-sans` in `resources/styles/00-tokens.css` is the single typography token for styled roots. The workspace inherits it rather than specifying another font. Poppins falls back to Segoe UI/system sans-serif when unavailable.

These external font and icon resources require internet access unless already cached. The compiled application layout stays local. Buttons retain visible text or accessible labels when the external resources cannot load; no passwords, authentication tokens, or document data are sent to these providers by the asset links.

The Content Security Policy permits the exact CSS/font origins needed by these links. Application scripts remain same-origin; inline scripts/styles, arbitrary external scripts, object embeds and framing remain blocked.

### Shared icon component

`application/config/icons.php` maps stable application names to Font Awesome Free classes. The actual `<i>` markup lives in `application/views/components/workspace/icon.php`; `icons.php` builds reusable HTML templates from the same component.

Use this inside a PHP view:

```php
<?php
$iconName = 'file';
require APPPATH . 'views/components/workspace/icon.php';
?>
```

In JavaScript, `icon('file')` clones that template. Extra CSS classes are added without removing the Font Awesome renderer/glyph classes. Icons are decorative (`aria-hidden="true"`); icon-only buttons must retain an accessible label.

The dashboard donut remains an SVG data chart, not an icon. Its geometry and live permission-filtered counts are unchanged.

## Where to design later

| Location | Responsibility |
| --- | --- |
| `resources/styles/00-tokens.css` | Brand, typography and shared surface tokens |
| `resources/styles/10-components.css` | Reusable inputs, buttons, tables and dialogs |
| `resources/styles/20-layout.css` | Base responsive shell |
| `resources/styles/90-fontawesome.css` | Scoped icon alignment/sizing |
| `resources/styles/modules/` | Isolated module extensions |
| `resources/workspace/reference.css` | Screenshot-based login/dashboard layout |
| `application/views/components/assets/styles.php` | Shared external stylesheet/font links |
| `application/config/icons.php` | Shared icon-name mappings |

## Verification

`tests/frontend_assets.php` checks the exact dependency/lockfile version, view-owned assets/icons, escaping, global-off behaviour and CSP boundaries. `tests/frontend_assets_browser.py` checks actual font loading, repeated icon updates, mobile overflow and blocked-CDN fallback against an isolated CI3/MySQL installation. Existing native/styled UI, document visibility and workflow tests remain in CI.
