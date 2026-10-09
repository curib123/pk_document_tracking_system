# Uploaded pk_dts.sql — Source of Truth

This rebuild follows the phpMyAdmin export uploaded by the user, generated **October 9, 2026, 03:08 AM** from MariaDB **10.4.32**. The normalized Git blob of the uploaded source is `65660f3a1dc48874c86540db5728156fd31f587d`. The original export has 29 tables, 298 column definitions, and 60 named foreign keys.

The authoritative installation files retain the uploaded schema, **but not exported live account data**:

- `pk_dts.sql` — all uploaded table definitions, indexes and foreign keys; historical INSERT statements are removed.
- `source_schema_contract.json` — expected names, exact column definitions and foreign-key references.
- `seed.sql` — static original roles, permission catalogs, role permissions and UI setting.
- `seed_workflows.sql` — nine original default approval graphs, dynamically assigned to a newly created administrator, not to an assumed user ID.
- `../tests/source_schema_contract.php` — checks schema fidelity and rejects committed account and login-attempt rows.

## Installation order (new installations only)

1. Create an **empty** `pk_dts` MariaDB/MySQL database.
2. Import `pk_dts.sql` once.
3. Import `seed.sql` once.
4. Create the first administrator through `php tools/create_admin.php admin`.
5. Import `seed_workflows.sql` once.
6. Configure CodeIgniter's environment and the private storage paths.

**Never import the schema or seed files over a populated or production `pk_dts` database.** The original uploaded dump contains a password hash for an existing account and login-attempt metadata. Keep the original backup private and rotate pre-existing administrator passwords as needed. Existing documents, files, workflow history and live user accounts need explicit preservation and read-only schema comparison, not a new installation.

All new controllers and models must use the uploaded tables directly. `sequences` has a string primary key `sequence_key` and a `value` counter; its Places screen must never assume `id` or `active`. Do not invent replacement `places`, `documents`, `request_details`, or `document_files` tables.
