# 04 — Database, backup, restore and recovery drill

**DANGER:** These steps replace data. Run only on a disposable QA instance and test database,
never a production or shared office installation.

## SQL / schema

| ID | Steps | Expected result |
| --- | --- | --- |
| DB-01 | Import `database/pk_dts.sql` into a brand-new empty QA database. | All tables, foreign keys, indexes and default roles/permissions created successfully. |
| DB-02 | Run migration smoke on an isolated DB using `bash tests/smoke.sh`. | Seed idempotency, folder/location and workflow checks pass. |
| DB-03 | Check roles + permissions including files.history, files.print, files.download and backup edit/view. | All required actions exist and are independently assignable. |
| DB-04 | Apply incremental migrations on a copy of an older QA schema. | No dropped user data, no orphaned references, no unexpected duplicate rows. |
| DB-05 | Repeat migration/seed on QA copy and inspect counts. | No harmful duplicate or destructive reset. Never repeat a non-idempotent migration on real data. |

## Encrypted backup and restore

| ID | Steps | Expected result |
| --- | --- | --- |
| BAK-01 | Confirm private backup path is outside public web directory and signing/password env keys set. | Setup ready; archive cannot be downloaded through a public file URL. |
| BAK-02 | Trigger manual backup via UI as an authorized admin. | Encrypted ZIP appears once, correct size/timestamp, no plaintext SQL exposed. |
| BAK-03 | Filter backup table by name/type, 10/25/50/100 limit, sort and page. | Correct records and consistent pagination; filtered count matches archive list. |
| BAK-04 | Open Import and Restore dialogs and cancel them. | No data change or accidental dismissal on outside click / Escape. |
| BAK-05 | Import an altered ZIP or ZIP with wrong signing key into QA. | Import fails integrity checks; it never becomes an eligible restore candidate. |
| BAK-06 | Record test DB counts and checksum an uploaded test file; create backup; modify QA data. | Backup contains the initial records and file contents. |
| BAK-07 | Restore prior QA archive with admin password and typed RESTORE confirmation. | Pre-restore recovery snapshot created; DB and file checksums return to original; session invalidated. |
| BAK-08 | Simulate interruption/failure on a secondary QA clone, not the primary QA or production. | No mixed restored/live state; maintenance/recovery response is safe and logged. |
| BAK-09 | Run retention cleanup after creating recent and expired synthetic QA archives. | Only old items removed per configured policy; fresh recovery archive retained. |
| BAK-10 | Confirm 5 PM Asia/Manila scheduler is installed/enabled on QA host; observe a scheduled run. | New automatic backup timestamp reflects local 5 PM schedule. |

Also run `bash tests/backup_integration.sh` only where it is configured for the disposable database.
Record path, SHA and evidence in `05-results-template.md`.
