# 02 — Reproduce Known GitHub Actions Errors First

## CI-01 — Workflow Builder setup guide contract

**CI evidence:** PHP lint jobs at 8.0/8.2/8.4 fail in tests/plan_contract.php with RuntimeException: Workflow Builder must provide setup guidance. That test searches for workflow-setup-guide. The audited application/view/pages/workflow_builder/index.php does not contain that marker. The earlier development run failed with the same message.

**Manual steps:**

1. Checkout audited development SHA, install dependencies, run: php tests/plan_contract.php
2. Copy exact error and exit code to evidence worksheet.
3. Open tests/plan_contract.php and locate the check for workflow-setup-guide.
4. Open application/view/pages/workflow_builder/index.php and search for workflow-setup-guide.
5. In your disposable browser app, log in as an authorized workflow editor, open Admin → Workflows, choose a workflow and look for setup instructions. Capture what really appears.
6. Inspect a draft and the Add Approval Step/Validate & Publish controls, but do not publish to production.

**Expected:** a useful visible guide explains draft, role/user/requester/leader approvers and safe publish process. **Actual proven in CI:** missing required component contract. **Actual browser impact:** not yet tested.

**After reproduction, suggested fix:** implement an accessible real setup-help section with the expected marker, not an invisible test-passing dummy element. Rerun php tests/plan_contract.php, bash tests/run_unit.sh and mobile/keyboard manual checks.

## CI-02 — Fresh SQL import fails; real SQL error is masked

**CI evidence:** separate database job first prints "Schema, workflows, ... multiple Hardcopy documents passed" from tests/smoke.sh. Then tests/schema_install.py fails at the import of database/pk_dts.sql with subprocess.CalledProcessError. The script uses capture_output=True with check=True and does not show mysql stderr. Exact SQL failure/statement remains unknown.

**Manual steps — only on an isolated throwaway DB server:**

1. Read tests/schema_install.py and database/pk_dts.sql, including INSERT sections. Confirm QA host, client binary and that database creation/deletion is safe on this server.
2. Record your versions and environment variable names: PK_MYSQL, DB_HOST, DB_USERNAME and DB_PASSWORD (never print their secret values).
3. Run python3 tests/schema_install.py on QA only; capture first traceback and exit status. If server scope is uncertain, mark BLOCKED and STOP.
4. On your LOCAL QA COPY ONLY, instrument the script's subprocess.CalledProcessError handling to print sanitized stderr and return code, then run again. Use a try/except around subprocess.run; print exc.stderr.decode(errors='replace') with personal data redacted, then raise. Do not log the SQL dump or passwords.
5. Record first mysql error number, constraint/table, and actual failing SQL line. Only after this evidence identify whether it is a bad insert, foreign key ordering, incompatible SQL, or fixture mismatch. Do not assume a root cause before stderr.
6. Separately compare tests/fixtures/pk_dts.sql (used by smoke.sh) versus database/pk_dts.sql (used by schema_install.py). Different sources explain why one check can pass and another fail.

**Expected:** import and assertions pass for a fresh schema. **Actual CI:** import exits nonzero; exact SQL error hidden.

## DB-01 — Clean-install test contradicts populated SQL snapshot

**Confirmed by source reading:** tests/schema_install.py asserts COUNT(*) = 0 in users, requests, workflow_history, files, softcopy_documents, hardcopy_documents, task_notification_receipts, user_roles. database/pk_dts.sql contains INSERT INTO for multiple application tables, including users/requests/files/softcopy/hardcopy records. Even if the import is repaired, the clean-install assumptions do not match the populated dump.

**Manual reproduction without executing SQL:**

1. Search tests/schema_install.py for the list of asserted-empty tables.
2. Search database/pk_dts.sql for INSERT INTO statements for each of them.
3. Record table names only, not actual usernames, emails, file names or password hashes.
4. On QA **after resolving CI-02**, run SELECT COUNT(*) for those tables.
5. Agree an installation policy: public schema-only plus intentional static roles/permissions; keep operational backups private.

**Follow-up:** Review whether repository history contains real business or account data. Do not post dumps in issue comments. If records belong to real users, follow organizational security processes.

## QA-01 — Error diagnostics incomplete

Current tests/schema_install.py captures mysql stderr but fails with a generic Python exception. A future code change should log sanitized stderr/exit status while guaranteeing test database cleanup. Capture baseline error **before** changing this helper.

CI: https://github.com/curib123/pk_document_tracking_system/actions/runs/39369989105

Pre-fix run: https://github.com/curib123/pk_document_tracking_system/actions/runs/39365105474
