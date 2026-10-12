# PK Document Tracking System — Issue Audit

**Audited base:** master after merged Backup & Restore PR #34, merge commit 1047c7abd4a7c9d1751b3641e72e5b580ede3dd5.

**Purpose:** Document issues, inconsistencies, deployment prerequisites and recovery risks. **Nothing in this folder applies a fix to application source, schema, runtime configuration or stored user data.**

The audit is based on repository source, CI results and database schema, **not on a live authenticated browser session or your actual XAMPP production database**. A static audit cannot prove that every possible issue has been discovered or promise 100% correctness. The fixes here are proposed starting points with copyable code; validate against a backup and disposable test installation before production.

## Issue index (one file per finding)

| ID | Priority | Type | Summary |
|---|---|---|---|
| [001](001-hardcopy-location-one-document.md) | High | Confirmed business-rule conflict | One Hardcopy document allowed per Location despite multi-file folder requirement |
| [002](002-unscoped-folder-metadata.md) | High | Permission/privacy concern | Folder metadata names are not scoped to the document viewer |
| [003](003-backup-dir-can-exclude-private-files.md) | High | Confirmed | A custom backup directory can exclude live private files |
| [004](004-restore-leaves-orphan-files.md) | High | Confirmed, intentional tradeoff | Restore overlays rather than exactly mirrors a private data snapshot |
| [005](005-restore-recovery-staging-cleanup.md) | High | Confirmed | Failed rollback can leave temporary decrypted recovery files |
| [006](006-zip-windows-path-validation.md) | High | Security hardening | Signed ZIP path validation lacks some Windows-specific safeguards |
| [007](007-maintenance-mode-login-gap.md) | High | Confirmed | Login can query the database during restore maintenance |
| [008](008-backup-binary-path-false-negative.md) | Medium | Confirmed | Client tools on PATH may be reported as missing |
| [009](009-daily-5pm-task-not-automatically-installed.md) | High | Deployment prerequisite | Merging code alone does not install the actual daily 5 PM task |
| [010](010-unbounded-backup-retention.md) | Medium-high | Confirmed policy gap | Full ZIP archives grow indefinitely |
| [011](011-php-dates-not-consistent-in-manila.md) | Medium | Confirmed | PHP date() calls use mixed host/local time assumptions |
| [012](012-transfer-destination-can-change-after-approval.md) | High | Confirmed race possibility | Destination parent hierarchy can change between approval and acceptance |
| [013](013-parallel-workflow-steps-duplicate-my-tasks.md) | Medium | Conditional | Parallel active steps may duplicate My Tasks records |
| [014](014-web-upload-cap-limits-disaster-recovery.md) | Medium | Known limitation | Browser ZIP uploads capped at 2 GiB and subject to HTTP limits |
| [015](015-no-alert-when-daily-backup-is-missed.md) | Medium-high | Confirmed missing feature | No warning that a scheduled backup was missed |
| [016](016-effective-date-optional-label-vs-required-schema.md) | Medium | Confirmed mismatch | Blank effective date becomes today's date instead of remaining blank |
| [017](017-json-audit-array-read-rewrite-scaling.md) | Medium | Scalability risk | Daily JSON audit array is fully reread and rewritten each append |
| [018](018-full-source-restore-requires-offline-step.md) | Medium | Intentional limitation | Browser restore does not restore running executable application files |
| [019](019-full-backup-is-not-point-in-time-atomic.md) | Medium-high | Confirmed limitation | Database and file backup may represent different moments |
| [020](020-root-brand-color-contrast-inconsistency.md) | Medium | Confirmed UI inconsistency | Root brand colors lack a consistent darkness scale and some text colors fail AA contrast |

## Recommended implementation order

First address **003, 005, 006, 007**, followed by **009, 015, 019** for backup safety and reliability. Decide the office's custody rule in **001** before changing the unique Location index. Address privacy and transfer consistency (**002, 012**) next. Remaining findings are UX, scale or situational issues.

**Dependencies:** Issue 001 affects the occupancy rule referenced by Issue 012. Issue 004 is not an authorization to delete orphaned files. Issue 016 requires an explicit policy choice between a default effective date and nullable dates in the schema.

## How to manually apply one proposed fix

1. Back up the live system and download/verify the ZIP. Perform code and SQL testing on a disposable XAMPP clone.
2. Read the individual issue file, including its prerequisites and warnings.
3. Create a separate branch per issue so changes are isolated:

~~~bash
git checkout master
git pull --ff-only
git checkout -b fix/issue-001
~~~

4. Copy only the code/SQL applicable to that issue into the **specified paths**. Do not paste a documentation-only CLI snippet into a PHP class.
5. Run PHP syntax and existing automated contracts:

~~~bash
php -l application/services/backup/backup_service.php
bash tests/run_unit.sh
~~~

6. If changing SQL, execute changes against a separate *_test database and run the existing schema/database smoke tests. Do **not** run schema installation against production; never assume a DDL migration is reversible.
7. Manually exercise the affected workflow in a signed-in browser (including mobile layout), check audit history and role permissions, and inspect real database results.
8. Commit and review before merging. Keep a verified recovery ZIP and database fallback plan.

## Important distinctions

- **Confirmed issue:** supported directly by current code/schema. The production impact may still depend on local data.
- **Conditional:** occurs only when a particular feature or concurrency pattern exists.
- **Intentional limitation:** not necessarily a bug; requires an explicit product/security decision.
- **Deployment prerequisite:** code is present, but XAMPP host setup is separate.

## Audit coverage and limitations

Reviewed: auth/permissions, Hardcopy/Softcopy, folders/Place hierarchy, workflow/task queries, transfers, dashboard dates, JSON audit writing, encrypted backup/restore, 5 PM scheduler, document files and request-related routes. Existing CI tested the merged system, including MariaDB encrypted ZIP restore. **No fix code in these issue files has been independently applied and verified as an integrated system**, so do not treat snippets as guaranteed production patches.

This is a **prioritized audit**, not a claim that the entire system is free of all other defects. The local Windows scheduler, backup directory permissions, account entitlements and live browser behavior remain to be checked on your real installation.
