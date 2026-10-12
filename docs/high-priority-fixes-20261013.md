# High-priority remediation — October 13, 2026

Implemented issues **001, 002, 003, 005, 006, 007, and 012**. Other findings remain open.

## Completed code changes

001 — Remove the one-Hardcopy-per-Location rule: a non-unique lookup index and changes to the Hardcopy save/transfer services. Separate records retain separate holders and approval history.

002 — Filter Folder Tree nodes to the ancestors of documents accessible to the signed-in non-administrator, across all pages; reject forged out-of-scope folder URLs.

003 — Reject backup directory settings that overlap live source/private data, while permitting the dedicated storage/backups directory or a separate private drive.

005 — Remove decrypted recovery staging in a finally block even when automatic recovery fails. The maintenance flag remains on incomplete rollback.

006 — Reject dangerous ZIP entry paths, including Windows device names, alternate stream colons, traversal paths and empty path segments.

007 — Check the restore-maintenance flag at the beginning of MY_Controller construction before any database-backed identity reads, including login.

012 — Reject transfer acceptance if approved Area/Specific/Asset relationships have since changed.

## IMPORTANT: Existing XAMPP database must be upgraded ONCE

Merging code does NOT modify the office MariaDB installation. The old UNIQUE index continues blocking multiple documents in one location until you run the migration.

1. Create, download and verify a full encrypted backup of the office database and files.
2. Test this migration on a cloned MariaDB installation before modifying production.
3. Inspect the target schema:

~~~sql
SELECT DATABASE();
SHOW INDEX FROM hardcopy_documents WHERE Column_name='location_id';
~~~

4. If UNIQUE KEY location_id is present, execute the reviewed one-time SQL file:
   database/migrations/20261013_allow_multiple_hardcopies_per_location.sql

Example from an elevated PowerShell on the actual XAMPP host (adjust paths and database name):

~~~powershell
$mysql = "C:/xampp/mysql/bin/mysql.exe"
$sql = "C:/xampp/htdocs/pk_document_tracking_system/database/migrations/20261013_allow_multiple_hardcopies_per_location.sql"
Get-Content -Raw $sql | & $mysql -u root -p pk_dts
~~~

The server will prompt for the DB password rather than displaying it in process arguments. Never run this against the wrong database.

5. Check that the idx_hardcopy_documents_location index has Non_unique=1. Test two valid Hardcopy documents in one Location, separate holders, direct edits, transfers and approvals.

Fresh database installs from the updated database/pk_dts.sql already use the non-unique index.

The migration is not auto-run at startup to avoid unreviewed schema changes on live office records.

## Intentional pending/operational items

004 — Exact file pruning on restore is **not implemented**. Current restore preserves later orphaned files rather than deleting undocumented office data. Atomic directory swap/recovery would require a separate approved retention/safety design.

009 — The daily 5 PM scheduler still must be installed and tested on the Windows XAMPP host using tools/install_backup_schedule.ps1. GitHub cannot schedule the user's local computer.

019 — DB and private-file backups may still capture different moments if edits/uploads occur during a backup. Avoid concurrent writes while creating snapshots until a coordinated snapshot implementation is approved.

All remaining medium/minor issues are deferred. See issues/README.md.

## Validation

Run bash tests/run_unit.sh. Use bash tests/smoke.sh and bash tests/backup_integration.sh only on a disposable test database. CI exercises PHP 8.0, 8.2, 8.4 and MariaDB.

Before production deployment, manually test both permissioned user folder views, same-location documents, transfer acceptance when a Place changes, login denial during restore, and archive download/verification in a separate XAMPP test environment.
