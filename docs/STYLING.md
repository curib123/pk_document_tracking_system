# Modular styling — red design system

## Turn styling on or off

Edit `application/config/styling.php`. This is the single authoritative styling configuration.

```php
'enabled' => true,          // false: the entire app stays plain HTML
'shell' => true,            // sidebar/header design for enabled screens
'default_enabled' => false,// new/unregistered modules stay plain
'modules' => [
    'softcopy' => true,
    'hardcopy' => false,    // no design on this module
    'workflows' => true,
    // Keep the other existing module entries.
],
```

Use PHP booleans `true` and `false`, not quoted strings. Reload the page after editing. When a module is false, its content and the screen shell lose their scoped styles; native form controls, tables and headings remain usable. Other modules keep their settings. Dialogs inherit the originating module; record-detail dialogs use their requested module. Account dialogs use `account`.

The optional server environment variable `PK_STYLING_ENABLED=0` disables everything for troubleshooting. `1` enables the configured module map. This is a server environment override, not a new public setting or query parameter. The older database appearance JSON is retained for compatibility but does not control this layer. No database migration is needed.

## Design a module later

- Change brand colors, text, borders and surfaces in `resources/styles/00-tokens.css`.
- Change reusable buttons, tables, forms, status indicators and dialogs in `resources/styles/10-components.css`.
- Change the responsive shell in `resources/styles/20-layout.css`.
- Add focused extensions under `resources/styles/modules/`, always scoped to `.pk-ui[data-pk-module="your_module"]`.

For example:

```css
#content.pk-ui[data-pk-module="locations"] h2 {
  border-left: 3px solid var(--pk-brand);
  padding-left: 14px;
}
```

Register the module's boolean in the PHP config. The shared components already style its forms and tables. Do not duplicate component CSS in controllers or business services.

## Build and deploy

The compiled stylesheet `public/assets/css/app.css` is committed. XAMPP needs no Node installation, Tailwind CDN, external fonts or network access to display it.

Developers rebuilding styles use Node 18+:

```sh
npm install
npm run build:css
npm run check:css
```

The compiler is pinned to Tailwind CSS 4.1.10. This semantic-component build uses Tailwind `@apply`; it deliberately omits Preflight and global theme resets. Do not edit compiled CSS manually. Commit CSS source and the rebuilt file together. Modern browsers supporting CSS nesting, `:has()` and dynamic viewport units are expected.

## Boundaries and verification

`styling.js` owns presentation scopes, responsive navigation and dynamic dialog decoration. `api.js` emits screen/detail/metadata events only; endpoint paths, payloads, CSRF handling, permissions and workflow decisions are unchanged. Styling switches are not security permissions.

PHP tests cover strict flags and the master override. Browser tests compare disabled modules with native computed styles, navigate in both directions, verify dialog isolation, keyboard menu behavior and mobile overflow. Existing unstyled modal tests and live styled application tests run in CI. Screenshots in tests use synthetic demonstration rows, not production documents.
