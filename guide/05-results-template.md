# 05 — Manual QA results and sign-off

Copy this template to a dated report and fill it during testing. **Do not mark unexecuted cases PASS.**

- Date/time (Asia/Manila):
- Tester:
- Git commit SHA:
- Branch:
- PHP version / database version:
- Browser version / device:
- Test environment URL (do not include credentials):
- Dataset/fixture identifier:
- CI run URL and conclusion:
- Backups and restore verification evidence:

| Case ID | PASS / FAIL / BLOCKED / NOT RUN | Evidence (screenshot/log) | Ticket and reproduction notes |
| --- | --- | --- | --- |
| ENV-01–12 | NOT RUN | | |
| ACL-01–11 | NOT RUN | | |
| REQ-01–06 | NOT RUN | | |
| UI-01–17 | NOT RUN | | |
| DB-01–05 | NOT RUN | | |
| BAK-01–10 | NOT RUN | | |

## Defect reproduction form

- ID and severity (Blocker / High / Medium / Low):
- Preconditions / account permissions:
- Exact URL (without secrets):
- Steps:
- Expected behavior:
- Observed behavior:
- Console error / server log ID:
- Screenshot and timestamp:
- Suspected code location:
- Retested with commit:
- Final result:

## Release gate

- [ ] PHP 8.0 CI passing for this exact commit
- [ ] JavaScript and PHP unit/contract tests passing
- [ ] Disposable database migration and restore tests passing
- [ ] Permission negative tests passed (direct URLs, not just hidden buttons)
- [ ] Request View and live search accepted in browser
- [ ] No unresolved blocker/high-severity issues
- [ ] Independent production backup and rollback plan approved
- [ ] Reviewer approves merge to `development`

Signed by tester: ____________________
Date: ____________________
