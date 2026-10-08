# PK DTS — incremental normal MVC migration

This is a working migration branch, not a complete replacement yet. The original
CodeIgniter 3 workspace and its existing APIs are deliberately preserved until every
document-control action has feature parity and passes integration/browser tests.

## Target pattern

- Controller: validate HTTP method/CSRF, call a service, render a PHP view or redirect after POST.
- Service (application/libraries): permissions, validation, transactions, approval rules and audit.
- Model (application/models): database reads and writes via CodeIgniter Query Builder.
- View (application/views/web): Bootstrap classes and escaped PHP output, ordinary
  links and HTML forms. Migrated routes need no browser fetch/AJAX, custom JS, or REST API.
- Authentication: existing Auth_service handles rate limits, password changes and sessions.
- Bootstrap is currently loaded as CSS via CDN, as in the inventory reference;
  there is no Bootstrap JavaScript or custom CSS dependency. Offline deployments
  should vendor the official Bootstrap stylesheet locally before removing CDN usage.

## Functional implementation

Open /public/index.php/web for the native dashboard or /web/login for sign-in.

| Feature | Native MVC | Notes |
| --- | --- | --- |
| Login, logout, required password change | Implemented | Existing auth service |
| Dashboard | Implemented | Permission-scoped read service |
| Areas, specifics, assets, locations, categories | Implemented | Search, pagination, add, edit, guarded delete |
| Other record lists and detail pages | Read-only | Existing permission-scoped read service |
| Request creation/approval, workflow editing | Legacy only | Preserve approval transitions |
| Softcopy/hardcopy create and revise | Legacy only | Preserve audit and file access |
| Direct transfer/access/assignment/disposal | Legacy only | Preserve recipient/retention checks |
| User/roles/permissions management | Legacy only | Requires admin forms and safeguards |
| Notifications, uploads, exports and downloads | Legacy only | Requires native form and file flows |

The native sidebar links to the existing workspace for actions that are not yet
supported. Do not delete old JS/CSS, JSON endpoint registry or old routes until all
features are migrated and verified. Do not represent this stage as fully REST-free.

## Next milestones

1. Full browser authentication and catalogue smoke tests with XAMPP/MySQL.
2. Native request forms and versioned workflow editor, preserving Request_service and Workflow_service.
3. Native document, file, transfer, assignment, access and disposal flows.
4. Native role/user/permissions forms, notifications and reporting.
5. Once equivalent modules pass tests, remove APIs, JS bundles, custom CSS, build
   scripts and obsolete views; promote the native dashboard to the default route.

## Safety checks

- Catalogue writes use Catalog_service and existing database transactions.
- Every POST requires session CSRF. GET requests cannot mutate records.
- Read_service enforces module permissions, document visibility and request ownership.
- Edit/delete capabilities are checked before displaying forms.
- Updates and deletions include record versions for optimistic concurrency checks.
- Escape every displayed value and show foreign-key names wherever available.
- Preserve database schema, migrations and production data.
- Run php tests/web_mvc.php, PHP lint, domain tests and browser regression suite.
- Verify compatibility with PHP 8.0 before merging.

## Repository cleanup (2026-10-08)

Removed the completed one-time `refactor/php-view-templates` GitHub Actions
bootstrap and its four compressed edit-payload chunks. They were only invoked
by workflows scoped to that old, isolated refactor branch; they are not used
by runtime CodeIgniter routes or ongoing test workflows.

The legacy workspace and its runtime JS/CSS, CSS build scripts, PHP templates,
API registry, installers, schema upgrade scripts and regression tests are **not**
obsolete yet, because production flows still use them. Delete them only after
feature-equivalent native MVC replacements have passed end-to-end verification.
