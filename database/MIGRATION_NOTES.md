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
