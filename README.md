# PK Document Tracking System

A server-rendered CodeIgniter 3 document-control workspace. Design: red and white enterprise UI, navy sidebar, reusable data tables and modal forms. Database access is performed through CI models and PHP services; **no REST API** is used.

## Before installing

The original repository did not include an existing database export. Its `database/schema.sql` and `database/seed.sql` form a **new baseline**, not a verified migration of the older DTS application. We identified the legacy source at [pk-dts-monorepo](https://github.com/curib123/pk-dts-monorepo), whose schema uses different column names and more specialized tables. **Never import this new baseline over a live legacy DTS database.** See `database/MIGRATION_NOTES.md`.

## Local installation (XAMPP)

1. Install PHP 8.x with `mysqli`, `mbstring`, `fileinfo` and `zip`; enable Apache `mod_rewrite`.
2. Run `composer install` at the repository root. CodeIgniter is installed into `vendor/`.
3. Create an **empty** MySQL database named `pk_dts`, then execute `database/schema.sql`, `database/migrations/20261009_document_workflows.sql`, and `database/seed.sql` **in that order**. The additive migration is required for attachments and request actions.
4. Create the first admin in a terminal: `php tools/create_admin.php admin admin@example.com`. It prompts for the name and a 12+ character password; no default account is seeded.
5. Configure a strong random `PK_ENCRYPTION_KEY` in the PHP/Apache process environment and review the connection in `application/config/database.php`.
6. Point the Apache virtual host DocumentRoot to the project's `public/` folder, not the repository root. Browse to `/login`.
7. Ensure `storage/sessions`, `storage/logs` and `storage/documents` are writable by Apache, but inaccessible over HTTP. Configure `upload_max_filesize` >= 15M and `post_max_size` >= 16M in PHP and restart Apache.

The existing login imagery is in `public/assets/images/`. Bootstrap, jQuery, Chart.js and Font Awesome are loaded through CDN URLs, so offline intranet installations must vendor these assets locally and change the layout references.

## Architecture

- `application/controllers/`: web page routes, permission guards, form handling and redirects.
- `application/models/`: database reads and query construction.
- `application/services/`: database changes and validation of business rules.
- `application/view/layout/`: shared header, sidebar, top bar, footer and confirmation modals.
- `application/view/components/datatable.php`: shared search, filters, sortable columns, actions, page limits and pagination.
- `application/libraries/Table_pager.php`: centralized allowlisted query-state parsing and page-bound clamping.
- `public/assets/css/app.css` / `public/assets/js/app.js`: visual style and progressive UI interactions.
- `database/`: new schema and idempotent base role/permission seeds.

### Database-backed DataTables

All **My Requests, My Tasks, Users, Roles, Workflows, Hardcopy/Softcopy Documents and six Places tabs** use SQL `COUNT(*)` and `LIMIT/OFFSET` with stable order-by columns. No table fetches all records for PHP `array_slice()`. Every table supports 10, 25, 50 or 100 rows per page and GET-based search, filter and column sorting. Pagination links preserve active search/filter/sort/limit settings, and out-of-range page numbers automatically clamp to the last page.

The reusable headers accept only allowlisted sort keys and `asc`/`desc`, so raw query parameters are never used as SQL column expressions. Workflow approval steps and role-permission assignments are fetched only for records visible on the current page. Form dropdown options are separate reference data, not paginated table rows. All requests remain traditional PHP page loads and HTML form submissions, **without REST APIs or AJAX**.

### Available modules

Dashboard; hardcopy and softcopy document registers; own requests and assigned tasks for all five request types; six reference-data tabs; user management; role-permission matrix; workflow versions, cloning and step editing. Approved requests can **create, revise and dispose documents; transfer hardcopies; assign a responsible user; or grant time-limited private file access**. Softcopy uploads are stored privately with version history. Document disposal and user/place deactivation preserve references.

Each access is enforced by `MY_Controller::require_permission`, with matching action visibility in the views. CI sessions, CSRF form tokens, prepared parameterized queries, one-time confirmation forms and password hashing protect normal web actions. A workflow version that already has requests cannot have its steps changed.

## Quality checks

- `php tests/static_contract.php` — structural and route/security contract checks.
- `php tests/lint.php` — PHP syntax checks (requires a CLI PHP executable).
- `composer test` — structural tests.
- `composer run test:lint` — syntax checks.
- `bash tests/integration_smoke.sh` — **CI-only disposable MySQL test**, using a temporary `pk_dts_test` database and built-in HTTP server. Do not run on a database with real records. Includes `tests/pagination_flows.sh`, which creates 37 fixtures per module to verify SQL page boundaries, search/filter, sorting, row limits and permission isolation.

GitHub Actions executes PHP lint and static checks plus a disposable MySQL HTTP integration suite that exercises login, server-enforced role permissions, all 22 main routes, document file uploads and guarded downloads, create/revise/dispose/transfer/access-grant/assignment effects, and workflow cloning.

**Integration status:** Automated database smoke tests passed against the new starter schema. They do not establish full UI/accessibility QA, large-file resilience, production migration compatibility, or concurrency behavior beyond guarded transaction paths. Verify manually in XAMPP before cutover. `php tools/audit_legacy_schema.php` can inspect a legacy MySQL schema read-only when supplied environment credentials; it does **not** migrate user data.

## Safe operational rules

- Do not apply the baseline schema to a production database with legacy tables.
- Never enable public web access to the `database/`, `storage/`, `application/` or `vendor/` directories.
- Give users only necessary permissions; system administrator permissions are intentionally unrestricted.
- Request remarks and workflow decision remarks are optional.
- Never put password hashes, connection credentials or user IDs in the visible data tables.
