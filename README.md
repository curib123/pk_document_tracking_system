# PK Document Tracking System

A server-rendered CodeIgniter 3 document-control workspace. Design: red and white enterprise UI, navy sidebar, reusable data tables and modal forms. Database access is performed through CI models and PHP services; **no REST API** is used.

## Before installing

The original repository did not include a production database export. The `database/schema.sql` and `database/seed.sql` files are a **new baseline**, not a verified migration of an existing DTS installation. Import these into a **new/empty** database only. If you already have live records, back them up and map your actual tables before attempting a migration.

## Local installation (XAMPP)

1. Install PHP 8.x with `mysqli`, `mbstring`, `fileinfo` and `zip`; enable Apache `mod_rewrite`.
2. Run `composer install` at the repository root. CodeIgniter is installed into `vendor/`.
3. Create an **empty** MySQL database named `pk_dts`, then execute `database/schema.sql` and `database/seed.sql` in that order.
4. Create the first admin in a terminal: `php tools/create_admin.php admin admin@example.com`. It prompts for the name and a 12+ character password; no default account is seeded.
5. Configure a strong random `PK_ENCRYPTION_KEY` in the PHP/Apache process environment and review the connection in `application/config/database.php`.
6. Point the Apache virtual host DocumentRoot to the project's `public/` folder, not the repository root. Browse to `/login`.
7. Ensure `storage/sessions` and `storage/logs` are writable by Apache. The application creates them on first startup.

The existing login imagery is in `public/assets/images/`. Bootstrap, jQuery, Chart.js and Font Awesome are loaded through CDN URLs, so offline intranet installations must vendor these assets locally and change the layout references.

## Architecture

- `application/controllers/`: web page routes, permission guards, form handling and redirects.
- `application/models/`: database reads and query construction.
- `application/services/`: database changes and validation of business rules.
- `application/view/layout/`: shared header, sidebar, top bar, footer and confirmation modals.
- `application/view/components/datatable.php`: shared search, filters, table, actions, page limits and pagination.
- `public/assets/css/app.css` / `public/assets/js/app.js`: visual style and progressive UI interactions.
- `database/`: new schema and idempotent base role/permission seeds.

### Available modules

Dashboard; hardcopy and softcopy document registers; own requests and assigned tasks for all five request types; six reference-data tabs; user management; role-permission matrix; workflow version and step editing. Document disposal and user/place deactivation preserve references. Requests are drafted, submitted to the active default workflow, and approved, rejected or returned by the configured approver.

Each access is enforced by `MY_Controller::require_permission`, with matching action visibility in the views. CI sessions, CSRF form tokens, prepared parameterized queries, one-time confirmation forms and password hashing protect normal web actions. A workflow version that already has requests cannot have its steps changed.

## Quality checks

- `php tests/static_contract.php` — structural and route/security contract checks.
- `php tests/lint.php` — PHP syntax checks (requires a CLI PHP executable).
- `composer test` — structural tests.
- `composer run test:lint` — syntax checks.
- `bash tests/integration_smoke.sh` — **CI-only disposable MySQL test**, using a temporary `pk_dts_test` database and built-in HTTP server. Do not run on a database with real records.

GitHub Actions executes both static checks and a MySQL-backed smoke test for login, server-enforced role permissions, all main page routes, creating reference data, creating and submitting a request draft, assigning its configured approver and recording approval.

**Integration status:** Automated smoke tests passed on a fresh schema; they do not prove full visual QA, file-handling capabilities, migration compatibility, concurrent workflow execution or production readiness. Verify end-to-end behavior on XAMPP and map real DTS data before deployment.

## Safe operational rules

- Do not apply the baseline schema to a production database with legacy tables.
- Never enable public web access to the `database/`, `storage/`, `application/` or `vendor/` directories.
- Give users only necessary permissions; system administrator permissions are intentionally unrestricted.
- Request remarks and workflow decision remarks are optional.
- Never put password hashes, connection credentials or user IDs in the visible data tables.
