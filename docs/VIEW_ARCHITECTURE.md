# PHP-owned pages and reusable components

## Responsibility boundary

HTML and SVG markup live in `application/views/`. JavaScript binds API data,
attaches event listeners, controls visibility, and clones existing templates.
It does not create HTML tags, parse HTML strings, or construct SVG paths.
The existing API, controllers, permissions, request lifecycle and schema are unchanged.

This remains an interactive application, not a full-page-reload conversion.
The server renders inert HTML `<template>` elements through the normal CI3 view
pipeline. Authorized API responses supply record data after sign-in. A template's
presence is not permission to view records or execute an action.

## Where to edit

| Interface | View |
| --- | --- |
| Login page and login controls | `pages/account/login.php` |
| Styled dashboard | `pages/dashboard/index.php` |
| Plain dashboard | `pages/dashboard/native.php` |
| Each module's page | `pages/<module>/index.php` |
| Header and account menu | `components/navigation/header.php` |
| Sidebar and signed-in footer | `components/navigation/sidebar.php` |
| Shared list toolbar, filters and pagination | `components/records/list.php` |
| Table, rows, cells and empty state | `components/records/table.php` |
| Native dialog shell | `components/dialogs/modal.php` |
| Lookup, upload and other form fields | `components/forms/fields.php` |
| Workflow editor | `components/workflows/version.php` |
| Role permission editor | `components/permissions/editor.php` |
| SVG icon definitions | `components/workspace/icons.php` |
| Recent-document row | `components/workspace/recent_documents.php` |

All paths in this table are relative to `application/views/`.
Shared markup is included by PHP instead of duplicated in every page. The fixed
include list in `components/registry.php` registers the templates; no request
parameter is interpreted as a view path. `modules/index.php` remains the page
mount point for the existing in-page navigation.

## JavaScript binding

`public/assets/js/views.js` provides the small shared binding layer:

```js
const page = cloneView('page-softcopy-template');
const control = page.querySelector('[data-page-search]');
control.addEventListener('submit', onSearch);
```

`cloneView` only accepts an existing template. `bindText` and `viewText` assign
text with `textContent`, including values containing angle brackets.
`setAttributes` refuses HTML-content properties and string event handlers.
`app.js` owns page events and action choices; `forms.js` binds schema-defined
fields to PHP field templates; `workspace.js` updates the reference dashboard
and login; `workspace-icons.js` clones PHP-authored SVG icons.

Keep template IDs and `data-*` hooks stable when changing layouts. The same
record form still serves create/edit requests and direct actions, preserving
request-type presets, optional remarks, hierarchy population and permission checks.
Module View permissions still govern navigation. The limited request catalog
still permits Access/Assignment selection without granting document content.

## Styling and deployment

`application/config/styling.php` still controls each module's true/false styling
switch. CSS remains in the existing `resources/styles/` and
`resources/workspace/reference.css` sources with committed compiled output.
This refactor does not change those flags or stylesheets. A module set to false
still uses its PHP view with native controls.

HTML-only changes do not require a CSS rebuild. Pull the reviewed code and reload
or hard-refresh the browser. No database migration, npm runtime, or new package
is required by the view refactor. The new PHP templates must be deployed together
with their JavaScript bindings; do not deploy only one side.

## Verification

Run the view ownership checks with `python3 tests/view_architecture.py`.
The browser suites `modal_browser.py`, `styling_browser.py`, and
`view_templates_browser.py` render the real PHP views and run the real frontend
controllers against explicitly simulated API responses. They verify forms,
module navigation, optional remarks, workflow controls, styling isolation, safe
text binding, login, dashboard, profile and mobile layout. These optional view-only checks exercise the legacy workspace while the new MVC
screens are migrated. Database-backed verification remains the responsibility of
the existing MySQL/controller workflows. The completed one-time view-refactor
bootstrap workflow and compressed payload are no longer retained on this branch.

The new **database/schema.sql** contains clean v7 table definitions (no exported
users or business records). Fresh installations now use **database/install.php**,
which applies **database/seed.sql** and generates the initial administrator password
through the PHP seeder. All database tests should use this clean schema rather
than attempting to restore a historical database dump.
