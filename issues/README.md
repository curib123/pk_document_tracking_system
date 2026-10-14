# PK Document Tracking System — Current Issues Audit

**Audit date:** October 14, 2026 (Asia/Manila)  
**Audited baseline:** master at 83a0d5fc1ca022731f63afc802def3206b459639 (PR #40 already merged)  
**Scope:** Static source review of latest CodeIgniter 3 MVC controllers, services, views, JavaScript, backup/restore logic, MariaDB schema and tests. **No live XAMPP/browser access was used.**  
**Fix status:** Every issue is a DOCUMENTED PROPOSAL only. No PHP, CSS, JS, routes or SQL is changed by this audit.

Each numbered file contains source evidence, explanation of risk, a copyable patch or a deliberately non-destructive diagnostic/setup procedure, and concrete verification steps. Code blocks are tailored to the source audited here, **but are not guaranteed to work in an untested production configuration**. Verify on a clone before merging.

## One issue per file

| File | Priority | Summary |
|---|---|---|
| [001](001-backup-write-actions-require-only-view-permission.md) | High · conditional | Backup View permission also authorizes create, cleanup, import, restore |
| [002](002-preexisting-request-drafts-can-target-another-user.md) | High | Legacy Access/Assignment drafts retain arbitrary recipient at Submit |
| [003](003-location-bulk-assignment-conflicts-with-active-transfers.md) | High | Bulk Location assignment can block approved physical handoffs |
| [004](004-location-bulk-assignment-missing-audit-event.md) | High | Location-wide holder updates have no durable custody activity entry |
| [005](005-retention-can-delete-final-backup.md) | Critical | Manual retention can delete the last available recovery ZIP |
| [006](006-backup-published-before-zip-integrity-verified.md) | High | Archive is published without post-close signed-content verification |
| [007](007-backup-db-and-documents-not-atomic.md) | High · architectural | SQL dump and document bytes are captured at different moments |
| [008](008-notification-inbox-looks-empty-without-migration.md) | High · deployment | Missing notification receipts migration silently looks like zero work |
| [009](009-password-minimum-counts-bytes-not-characters.md) | Medium | Nine-character password rule uses byte length |
| [010](010-unread-notification-pagination-race.md) | Medium | Rapid Next/Rows changes lose requests on slow networks |
| [011](011-12hour-formatting-depends-on-javascript.md) | Medium | AM/PM is generated only by browser DOM mutation, not the server |
| [012](012-approval-expiration-dates-use-server-clock.md) | Medium · timezone conditional | Access expiry mixes PHP local clock with Manila date rules |
| [013](013-controlled-file-print-does-not-open-print-dialog.md) | Low–Medium | Print currently opens preview rather than invoking a dialog |
| [014](014-audit-daily-json-rewritten-on-every-action.md) | Medium · scaling | Audit JSON rewrite scales poorly as daily log grows |
| [015](015-automatic-audit-retention-triggered-by-any-web-request.md) | High · policy | Ordinary HTTP bootstrap can delete expired evidence and ZIPs |
| [016](016-daily-5pm-backup-still-requires-host-scheduler.md) | High · operational | 5 PM backup requires installation on actual XAMPP server |
| [017](017-restore-overlays-instead-of-exact-private-file-snapshot.md) | High · intentional limit | Restoration leaves later unreferenced private bytes |
| [018](018-backup-setup-can-falsely-show-missing-db-client.md) | Medium | MariaDB executables found via PATH may show as missing |

## Suggested order

**Priority 0 — protect recoverability:** 005 (never prune final recovery archive), 006 (verify completed ZIP before publication), 007 (design a coordinated DB/file snapshot), 015 (review HTTP-triggered audit cleanup), 016 (install and verify host scheduler), 017 (reconcile orphan bytes after restore).

**Priority 1 — authorization and business records:** 001 (separate backup read/write permissions), 002 (normalize older self-service drafts before submit), 003 (block Location bulk reassignment during in-flight transfers), 004 (record custody audit), 008 (make missing notification migration visible).

**Priority 2 — correctness, UX and scalability:** 009, 010, 011, 012, 013, 014, 018.

**Dependency notes:** 003 and 004 both alter the same Administration::assign_location() method: reconcile into one patch, with custody lock/validation before trans_commit() and a reliable audit after commit. 005, 006, 007, 015, 016 and 017 are related backup changes: choose a recovery/retention policy before introducing any deletion. 002 concerns existing draft/returned requests, not already-approved historical records.

## How to apply one proposed patch safely

1. On XAMPP, create an encrypted system ZIP plus a separately verified DB backup and test the ZIP by restoring it on a disposable clone. Save it off the machine.
2. Read the issue file's **Status**, **Evidence**, **Copyable fix** and **Verify** sections. Some architectural issues contain *diagnostics* rather than a deployable one-line change.
3. Work on a dedicated branch and edit ONLY the paths listed in that issue:

~~~bash
git checkout master
git pull --ff-only
git checkout -b fix/issue-002
~~~

4. Copy the applicable snippet at the specified point. Preserve the existing surrounding authorization checks, CSRF, CodeIgniter models/services and transactions. Do not paste illustrative pseudocode as a new endpoint.
5. Run the existing tests and add a regression test for the specific reproduction before considering merge:

~~~bash
bash tests/run_unit.sh
~~~

6. Execute database tests only on a disposable configured *_test DB, NEVER on your live office database. Practice all affected workflows with real browser roles, requests, ZIP sizes, unread alerts and concurrent users.
7. Review and merge only after CI passes AND an operator signs off on any data-retention, custody or security-policy decision.

## Accuracy and limits

- **Confirmed source gap** means the code currently has the described ordering, missing validation, or behavior. Impact may be conditional on deployment and data.
- **Operational** means source code exists but only the Windows server administrator can activate or verify it.
- **Architectural/intentional** means there is no safe isolated patch that guarantees a full fix; use the included diagnostic and complete design/recovery testing first.
- Earlier GitHub issues or PRs may have addressed different versions of the repository; this index is anchored to the latest master commit shown above.

Automated CI for this documentation branch checks that production code is unchanged. A passing baseline test suite **does not prove that these suggested fixes have been executed or tested together**.
