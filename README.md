# PK Document Tracking System

CodeIgniter 3 server-rendered rebuild started from the original master-branch empty-file module scaffold.

**Database source of truth:** The user-supplied October 9, 2026 phpMyAdmin export (normalized Git blob `65660f3a1dc48874c86540db5728156fd31f587d`). `database/pk_dts.sql` retains its **29 tables, 298 column definitions, indexes, and 60 foreign keys**, excluding exported data rows. `database/source_schema_contract.json` and `tests/source_schema_contract.php` enforce the uploaded structure.

**Never import the installation SQL files into an existing database.** The uploaded export contains a historical administrator password hash and login-attempt metadata; the GitHub install files omit those sensitive rows. Back up real users, attachments, requests and workflow history before any live changes.

## Setup

1. Use PHP 8+, MySQL/MariaDB, XAMPP Apache with mod_rewrite and Composer.
2. Run `composer install` at the repository root.
3. On a **new empty** database import `database/pk_dts.sql` then `database/seed.sql`. The latter contains only original role/permission definitions and the appearance setting.
4. Create a first admin with `php tools/create_admin.php admin` using your own strong password. Then import `database/seed_workflows.sql` to install the uploaded nine default approval graphs, resolving the administrator ID dynamically.
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

## Mapping to the uploaded SQL

Documents use `hardcopy_documents`, `softcopy_documents`, `files`, and `softcopy_revisions`, not an invented shared `documents` table. Places use `areas`, `specifics`, `assets`, `locations`, `categories` and `sequences`. **The `sequences` table is a read-only system counter keyed by `sequence_key`; it has no numeric ID.** Requests use `requests`, `workflow_versions`, `workflow_steps`, and `workflow_history`, with controlled effects recorded in `transfers`, `assignments`, `access_grants`, `disposals` and `status_history`.

Read [database/SQL_SOURCE_OF_TRUTH.md](database/SQL_SOURCE_OF_TRUTH.md) before importing or modifying any schema. Functional tests run against disposable MariaDB, not the existing production database.

## October 9 form, access and audit fixes

- All editable records reopen the same upsert form with their saved values. Workflow approver steps can also be edited and re-ordered. Hardcopy Request keeps the same responsive Area/Specific/Asset/Location hierarchy and retention controls as Hardcopy Direct.
- Access Grant requests accept **Softcopy or Hardcopy** through `access_grants.domain` and `access_grants.document_id`, with a selectable recipient and expiry.
- Document Assignment requests accept both types. Existing `assignments.softcopy_id` stores softcopy access assignments; hardcopy assignment changes the source schema's `hardcopy_documents.holder_id`. Physical custody transfers remain a separate dispatch/acceptance operation.
- Administrators get **Assign Documents** at `/admin/document-assignments`. The page shows both sources and provides editable assignment forms without inventing new columns/tables.
- Disposal methods are `Shred`, `Scratch`, and `Other`. The Other option requires a description. The selected method is stored in original `disposals.disposal_action` and its details in `disposals.remarks`.
- Softcopy revision **date received** is server-controlled when saved; **date released** is stamped on final approval. **Effective date** is optional, defaulting to approval date if omitted. The original source columns remain non-null.
- General operation audit events are stored in **`storage/audit/YYYY-MM-DD.json`**, as valid per-day JSON arrays using file locks and atomic updates. Successful and failed form operations record an actor, route and timestamp; passwords, uploaded contents and raw request bodies are excluded. No new audit database table is added.
- Existing `workflow_history` and `status_history` tables are preserved for operational workflow and document history because they are part of the authoritative `pk_dts.sql`.

Do not serve `storage/` through Apache. Back up the audit folder with private storage and preserve its access controls. Automated database integration tests use a disposable `pk_dts_test` database; real Windows/XAMPP UI and production data must be verified before merging.
