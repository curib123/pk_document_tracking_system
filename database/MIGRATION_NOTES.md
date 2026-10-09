# Workflow completion: data mapping and safety

The current repository did not contain a legacy production SQL export. This release extends its **new starter schema** with `database/migrations/20261009_document_workflows.sql`. It is not a verified migration of another DTS database.

For a new installation, apply `database/schema.sql`, then `database/migrations/20261009_document_workflows.sql`, then `database/seed.sql`. For an existing installation built with this repository's starter schema, first back up the database and upload directory, then apply only the additive migration. Never run `schema.sql` against an unverified production schema.

## Request effects
- Softcopy or hardcopy requests can create, revise or dispose documents once every configured approval step succeeds. Request-level data is stored in `request_details`.
- Hardcopy transfer requests update document location and append `document_transfers` audit history.
- Access-grant requests add a time-bound or perpetual document-download grant for a specified user.
- Document-assignment requests update the responsible user and record the approval request.
- All final effects occur in the same database transaction as the final approval decision.
- `request_decisions` preserves decision history. Workflow versions in use cannot have their approval steps changed.
- New user and document permissions are checked on the server; hiding frontend actions alone is never sufficient.

## File safety
Softcopy uploads are private, stored below `storage/documents/` outside web root; the database stores only randomized storage names and sanitized original filenames. The download controller checks authentication and authorization on each request and serves attachments. Keep `storage/` private. Uploaded file extensions and MIME types are allowlisted and file size is capped. Never serve raw upload paths or expose private storage via Apache.

## Still requires verification before live migration
Importing existing users, documents, file attachments, approval histories and role assignments needs a **real database dump or schema**. The included upgrade is intentionally not allowed to guess how historical tables map. Beyond CI smoke checks, review the full request and upload flows in the actual XAMPP/Apache environment and provide automated backup/restore procedures before production use.

## Identified legacy DTS source schema

A separate [pk-dts-monorepo](https://github.com/curib123/pk-dts-monorepo) contains a detailed Prisma schema and an older SQL backup. These are **not** the same structures as this new CI3 baseline. Representative differences:

| Legacy structure | New CI3 starter structure |
| --- | --- |
| `users.user_id`, `firstname`, `lastname`, `password` | `users.id`, `name`, `password_hash` |
| `roles.role_id`, `role_name` | `roles.id`, `name` |
| `permissions.permission_id`, `module_key`, `action_key` | `permissions.id`, `module`, `action` |
| `areas`, `specifics`, `asset_numbers`, `locations`, `sequences` | One `places` table with a typed entry |
| Legacy document/revision/attachment and approval tables | `documents`, `document_files`, `requests` and related workflow tables |

The legacy application has additional modules and relationships that do not yet have one-to-one equivalents. Avoid copying the historical SQL backup into this repository or force-renaming live columns. Any real migration needs explicit field mapping, historical attachments, permission reconciliation, approval-history preservation, row-count checks and rollback tests.

Run `php tools/audit_legacy_schema.php` in a terminal with read-only legacy MySQL credentials supplied through `PK_LEGACY_HOST`, `PK_LEGACY_PORT`, `PK_LEGACY_DB`, `PK_LEGACY_USER` and `PK_LEGACY_PASSWORD`. Its report only checks expected table/column names; it does not extract any data.
