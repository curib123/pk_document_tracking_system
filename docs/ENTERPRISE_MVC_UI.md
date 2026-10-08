# Enterprise CodeIgniter 3 MVC UI

This update introduces a unified, modern SaaS-style interface for native
server-rendered pages. It does not remove the old API-driven workspace until
all remaining document workflows have full native replacements.

## Architecture

Normal MVC request flow:

Browser HTML form or GET link -> CI3 route bridge -> feature module controller
-> domain service -> Query Builder model -> MySQL -> PHP view.

No AJAX, fetch or REST calls are made by the new UI JavaScript. Validation,
authorization, approval rules, optimistic locking, audit and transactions stay
on the server, not in the browser. The existing Catalog_service and Read_service
remain the business boundary.

## Shared UI components

- application/views/web/header.php and footer.php: unified shell and assets
- application/views/web/components/sidebar.php: desktop and mobile navigation
- application/views/web/components/modal.php: all new alerts, confirmations and forms
- application/views/web/components/field.php: all catalogue field types
- application/views/web/components/searchable_select.php: native dropdown with jQuery search
- application/views/web/components/data_table.php: centralized search, filter, sort, table and pagination
- application/helpers/web_ui_helper.php: icon mapping, route helper
- application/helpers/web_display_helper.php: safe, readable record details
- public/assets/css/enterprise-ui.css: shared custom styling
- public/assets/js/enterprise-ui.js: local jQuery enhancement logic

New module pages should reuse these components rather than creating more
duplicate sidebars, tables or modal wrappers.

## Data table structure

The server renders search above filters, filters above table rows, and paging
and rows-per-page controls at the bottom. Every search, filter, sort and page
link uses a normal GET request handled by Read_service and Read_model. The
read model scopes results to user permissions and visibility before filtering.

## Searchable dropdown structure

The PHP view always renders a native HTML select element with option labels
from the server. When jQuery loads, the control initially looks like a select.
Clicking opens an editable search field with filtered option buttons.

For long catalogue lists, the Search full list control posts the existing
form to a normal CI3 controller action, not an API. The controller preserves
the form draft and redirects to the same form with filtered lookup values.
This also works with a plain native select when JavaScript is unavailable.

## Modal architecture

One common modal shell renders the title, icon, message, body, POST form
and footer. The native add/edit/delete routes open it on page load when
Bootstrap's JS is available. Without Bootstrap JS the same content remains
visible as an ordinary page form.

Alerts, confirmation screens and catalogue add/edit screens use the common
component. Actions use CSRF-protected POST requests. No client database
requests are made.

## Fonts, icons and offline behavior

- Font preference: Roboto; local Segoe UI, Arial and system UI fallback.
- Icon preference: Font Awesome; Bootstrap Icons fallback; Unicode fallback.
- Bootstrap CSS/JS and jQuery are vendored locally under public/assets/vendor, with MIT license files included. Roboto and icon fonts still use public CDN origins when connected.
- Local enterprise CSS/JS is tracked in the repository.
- Normal HTML forms and native selects remain usable with JavaScript disabled.
- With no internet, Bootstrap and jQuery still work locally. The icon/text fallbacks use Unicode and local system fonts; remote icon-font styling is optional.
- No font files are redistributed by this change.

## Scope boundaries

Converted: native dashboard shell, catalogue lists/forms, read-only record
lists and details, authentication inputs, shared modals and table controls.

Not yet converted: the original classic workspace's workflow editor,
request approvals, file operations, softcopy/hardcopy write flows, transfers,
disposals, user administration and reports. Those classic screens still
depend on their original scripts and endpoint registry. Remove those
dependencies only after a feature-by-feature replacement and regression
checks; otherwise important business functions would break.

## Verification

Run the independent checks:

- php tests/web_mvc.php
- php tests/modular_mvc.php
- php tests/enterprise_ui.php
- node --check public/assets/js/enterprise-ui.js

End-to-end MySQL/XAMPP testing of all business actions is still required
before promoting this PR into the default installation.
