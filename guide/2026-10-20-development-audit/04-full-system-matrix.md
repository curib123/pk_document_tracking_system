# 04 — Full-System Exploratory Manual Tests

These are **not confirmed defects**. Reproduce each on fake disposable data, fill evidence worksheet, mark PASS / FAIL REPRODUCED / BLOCKED / NOT TESTED. Reset the QA database between tests. Use separate A/B/C/D/E browser profiles.

| ID | Module | Recreate/check manually | Expected |
| --- | --- | --- | --- |
| AUTH-01 | Login | Incorrect password then correct QA password | No bypass or stale error |
| AUTH-02 | First login | New user must change temporary password | Old password stops working |
| AUTH-03 | Sessions | Admin deactivates A; A refreshes authenticated page | Server denies access |
| AUTH-04 | CSRF | Stale/missing token on synthetic protected form | No write; readable error |
| ACL-01 | Permissions | D with no files permission opens direct QA file/review/print URL | Server denies, not only UI |
| ACL-02 | Roles | Add/remove secondary role, re-login and view documents | Scope updates correctly |
| ACL-03 | Custody | Transfer B → C; revisit old holder B's document URL | Revocation/visibility correct |
| REQ-03 | Draft | Create/edit/cancel a draft for each request action | No stale data or hidden workflow |
| REQ-04 | Submit | Same draft submitted in two sessions | Exactly one active submission |
| REQ-05 | Approve | Two approvers race one workflow step | One decision, one history |
| REQ-06 | Reject | Reject softcopy file revision then try to review attachment | Permissions and file lifecycle correct |
| REQ-07 | Return | Return from first/third step, reapprove | Immutable history and route correct |
| REQ-08 | Datatable | Search, status, type, dates, pagination, title display | Correct counts and details |
| DOC-01 | Hardcopy | Create H with hierarchy Area/Specific/Asset/Location | Correct holder and location |
| DOC-02 | Hardcopy | Create 2 hardcopies at same Location | Both accepted and listed |
| DOC-03 | Retention | Enabled, disabled, expired QA date | Accurate countdown/no retention |
| DOC-04 | Disposal | Approve H disposal, inspect history, old link | Title persists, status & permissions correct |
| DOC-05 | Softcopy | Create, upload PDF, approve, open version history | Secure correct version |
| DOC-06 | Softcopy | Revise and cancel in independent QA cases | No stale file or wrong revision |
| DOC-07 | Upload | Unsupported MIME, oversized file, valid synthetic PDF | Validation works |
| DOC-08 | Modals | Open two different records, switch tabs, close/reopen | No stale record content |
| TR-03 | Transfer | Request, dispatch, accept with A/B/C | Proper stage and actor |
| TR-04 | Transfer | Double-dispatch/accept, wrong actor | No duplicate writes |
| TR-05 | Transfer | Change Location parent after approval in QA | Refuse silently changed destination |
| ADM-02 | Assignment | Assign single H, bulk Location, bulk category | Proper scope, permission and totals |
| ADM-03 | Empty | Assign All empty Location and category | Graceful error, no SQL warning |
| PLACE-01 | Places | Create/edit Area → Specific → Asset → Location | Parent hierarchy coherent |
| PLACE-02 | Sequence | Auto number and duplicate sequence same Specific | Stable order, duplicate denied |
| WF-01 | Workflow | Draft, add/move/remove approver, publish | Only draft editable, setup guidance visible |
| WF-02 | Workflow | Change published route after old request submitted | Original request snapshot retained |
| WF-03 | Workflow | Approver role has no active accounts | Readable error, no partial submit |
| NOTIF-01 | Notifications | New task in two browser profiles | Correct badge, single prompt |
| NOTIF-02 | Notifications | Decide task, reload and check badge | No stale notifications |
| AUDIT-01 | Audit | Create/approve/transfer/dispose in QA | Correct actor and dates |
| AUDIT-02 | Audit | Observe audit writer failure in synthetic QA | Warning, not false success |
| BK-01 | Backups | Make encrypted backup in QA, verify contents | Correct private archive |
| BK-02 | Restore | Restore QA archive to DIFFERENT QA instance | Data consistent; no production contact |
| BK-03 | Scheduler | Inspect QA scheduled backup at 5 PM | Configured timezone and logs |
| UI-01 | Responsive | 320/375/768/1440 widths, all modals | No clipping/horizontal trap |
| UI-02 | Keyboard | Tab, Escape, modal focus, screen reader labels | Accessible navigation |
| PWA-01 | Offline | Disconnect internet in QA PWA after load | Honest offline state; no false save |
| ERR-01 | Errors | Disconnect QA DB during read-only page view | Sanitized error and useful server log |

**Pass policy:** If required account/document/workflow/permission is missing, mark BLOCKED, fix the test setup and repeat. Only mark FAIL with expected-vs-actual evidence. No test should modify production records. Compare with tests/browser_*.py and tests/run_unit.sh; automated tests are complementary.
