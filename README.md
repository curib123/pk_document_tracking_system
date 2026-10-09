# PK Document Tracking System

CodeIgniter 3 server-rendered rebuild started from the original master-branch empty-file module scaffold.

**Database source of truth:** `database/pk_dts.sql`, recovered from historical commit `f232511d4684e844ad88dcdb9477a5046b84159c` (Git blob `6cc10eb338c39a454ae3cceffe185e2dfe31e67f`). The source DDL structure, columns, indexes, and foreign keys have been retained; historical INSERT statements were intentionally excluded to avoid exposing user/password data. Role and permission metadata is available separately in `database/seed.sql`.

**Do not import this schema into a populated or live database.** Create an empty `pk_dts` database for new installations. Existing databases should be backed up and checked against the recovered schema rather than overwritten.

## Setup

1. Use PHP 8+, MySQL/MariaDB, XAMPP Apache with mod_rewrite and Composer.
2. Run `composer install` at the repository root.
3. On a **new empty** database import `database/pk_dts.sql` then `database/seed.sql`.
4. Create a first admin with `php tools/create_admin.php admin`.
5. Set DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD and PK_ENCRYPTION_KEY in the PHP environment.
6. Point Apache DocumentRoot at `public/` and make `storage/` writable but not publicly accessible.
7. Sign in through `/login`.

## Architecture

- Native CI3 controllers → domain services → CI3 models → existing pk_dts tables.
- `application/view/` preserves the master layout/module file structure. Shared DataTables and modals are centralized in reusable_components.
- Design retains the red enterprise palette, white content, and dark navy navigation.
- No REST API. Page lists use SQL LIMIT/OFFSET and status/search query filters.
- Approval runtime references `requests`, `workflow_versions`, `workflow_steps` and `workflow_history`, with a graph snapshot per submitted request.

## Implemented modules and effects

- Red/navy SaaS layout, white topbar and content, responsive navigation and shared modal confirmation.
- Original-table hardcopy and softcopy document registers with separate location/category references.
- Six Places modules: area, specific, asset, location, sequence and softcopy category, with foreign-key relationships.
- My Requests and My Tasks using the real workflow and audit tables. The Request View modal includes approval history and optional remarks.
- Workflow Builder can create and clone versions, add and remove approval steps in draft, and publish immutable default versions. Approvers may be specific users, roles, requester or requester leader.
- Final approval writes document creation/update/cancellation, disposal, assignments and access grants to their original tables.
- A hardcopy transfer requires holder dispatch followed by named recipient acceptance; only acceptance changes the physical holder/location.
- Softcopy document files are private under `storage/documents/` and downloads require authorization. The schema's `files` and `softcopy_revisions` tables hold approved direct revisions or pending revision uploads that become approved after the correct workflow ends.
- Normal CI3 server form posts and SQL paginated DataTables; no REST or AJAX API endpoints.

## Verification

GitHub Actions validates PHP syntax, JS syntax, baseline schema and MariaDB integration using only a disposable `pk_dts_test` database. See `tests/smoke.sh`, `tests/workflow_smoke.sh` and `tests/places_smoke.sh` for exercised operations.

**Production/cutover limitations:** Automated tests cannot prove real Windows XAMPP UX quality, file security against all formats, concurrent approvals, full migration of historical attachment bytes, environment-specific access rights, or backup/restore recovery. The earlier incomplete PR #14 using invented tables must not be merged. Validate against the existing database backup, preserve original data, and complete a local browser audit before production replacement.
