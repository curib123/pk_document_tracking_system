# 05 — Debugging Playbook, Proposed Fixes and Evidence Template

## Where to look after you have a failing reproduction

| Failure symptom | First layer to inspect | Suggested fix only AFTER evidence |
| --- | --- | --- |
| Workflow Builder CI contract | tests/plan_contract.php; view/pages/workflow_builder/index.php | Add a genuine visible workflow-setup-guide section explaining draft, step types, publication and versioning; do not hide a dummy marker |
| SQL install error with no cause | tests/schema_install.py; database/pk_dts.sql | Catch subprocess.CalledProcessError and log sanitized stderr/exit status, rethrow and always clean up QA DB; then fix exact SQL error |
| Fresh install has prior documents | database/pk_dts.sql vs tests/schema_install.py | Create safe schema-only plus intentional static seed; keep live/operational backups private |
| Cannot search document title | Request_model::scope, listing | Plan source-title joins for BOTH count and listing; add escaped grouped LIKE and DB test |
| Cannot sort title | request/shared_index.php dt_sortable; Request_model listing sort map | If specified UX, implement server title sorting with stable tie-break and pagination |
| Transfer accept fails after admin assignment | Document_access_service::apply; Administration::save_document_assignment; Transfer_service::accept | Shared open-transfer guard under transaction for single and bulk hardcopy assignment |
| Two transfers compete for same H | Request_model::has_submitted_related_request; Request_service::applyApproved | Decide global custody lock policy before implementing; do not block unrelated access without agreement |
| Assign All with no H errors | Administration::assign_location | Check empty rows BEFORE applying where_in; return expected DomainException |
| Missing/stale modal title | request/shared_index.php; request_title.php; public/assets/js/document-view.js | Compare payload source title/record ID with DOM; regression by request type |
| Task badge wrong | Notification_model and task-notifications.js | Compare current active step and user roles; avoid duplicate counting |

## Safe SELECT-only QA queries

Replace IDs with synthetic QA IDs. Do NOT execute against production.

    SELECT id, reference, type, status, requested_by, hardcopy_id, softcopy_id
      FROM requests WHERE id=42;

    SELECT id, title, holder_id, location_id, status
      FROM hardcopy_documents WHERE id=9;

    SELECT id, request_id, hardcopy_id, status, current_holder_id,
           recipient_id, transferred_by, accepted_by
      FROM transfers WHERE hardcopy_id=9 ORDER BY id DESC;

    SELECT request_id, step_id, action, user_id, created_at
      FROM workflow_history WHERE request_id=42 ORDER BY id;

    SELECT COUNT(*) AS active_documents
      FROM hardcopy_documents WHERE location_id=99 AND status='active';

    SELECT COUNT(*) AS related_submitted
      FROM requests WHERE requested_by=8 AND hardcopy_id=9
        AND type IN ('access','assignment','transfer') AND status='submitted';

## Test commands

Run from repo root in supported PHP/Node environment:

    php tests/plan_contract.php
    php tests/plan_20261020_regressions.php
    bash tests/run_unit.sh
    node --test tests/live_table_contract.test.js

**Danger — DB mutating:** bash tests/smoke.sh and python3 tests/schema_install.py. Run only when you have verified target is disposable QA. No office/production DB. Capture first error, exit code and SHA.

## Copy this worksheet for each issue

### Identification
- Issue ID, title, date/time:
- Branch + exact SHA:
- Environment: XAMPP/PHP/MySQL/browser version:
- Reproduction status: NOT TESTED / PASS / FAIL REPRODUCED / BLOCKED:
- QA actor roles and synthetic document IDs:
- Priority P0/P1/P2/P3:

### 1. Expected outcome BEFORE testing
- Preconditions:
- What should happen:
- Source of requirement:

### 2. Exact manual steps BEFORE any fix
1. Restore disposable QA baseline.
2. Log in as:
3. Navigate to:
4. Input/select:
5. Click:
6. Repeat with:
7. Negative-control scenario:

### 3. Actual evidence BEFORE any fix
- UI error exact text:
- Network method/path/status:
- First browser Console error/stack:
- First PHP/application/Apache log:
- SELECT-only before/after:
- Screenshot/video files (redacted):
- Two attempts result:
- Did it happen on audited baseline? yes/no/untested:

### 4. Decide whether confirmed
- PASS / FAIL REPRODUCED / BLOCKED / NOT TESTED:
- Why actual differs from expected:
- Separate evidence from inference:

### 5. Root cause AFTER manual reproduction
- Exact file/function/line:
- Which layer failed:
- Minimal fix and why:
- Side effects/security/permissions to preserve:

### 6. Regression AFTER implementing fix
- Failing automated test added:
- Source commit SHA:
- Repeat exact steps before/after:
- Unauthorized user check:
- Other document / status control:
- Unit suite + CI status:
- QA release blocker?:
- Reviewer and approval:

## Completion rules

Only mark FIXED if the original failure is reproducible before the change, new focused test fails before/passes after, manual same-steps test passes, authorized and unauthorized actors behave correctly and history is preserved. If CI had unrelated baseline failures, record them openly; do not call the entire suite green. Keep every scenario as NOT TESTED until actually executed.
