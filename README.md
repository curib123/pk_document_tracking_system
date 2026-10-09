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

**Verification scope:** The restored schema and basic modules can be checked by the included tests. Full historical behavior (especially file revisions, transfer acceptance, and existing production data migration) needs additional integration QA before production deployment.
