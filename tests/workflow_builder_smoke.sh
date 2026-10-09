#!/usr/bin/env bash
# Tests the original pk_dts graph routing in a disposable MariaDB only.
set -euo pipefail

echo 'CASE: sequential Workflow Builder user -> role routing'
# Workflow definitions come only from database/seed_workflows.sql.
WF_ID=$(db "SELECT id FROM workflows WHERE workflow_key='softcopy_cancel'")
test -n "$WF_ID"
WORKFLOWS_BEFORE=$(db "SELECT COUNT(*) FROM workflows")
TOKEN=$(token_for admin/workflows)
DENY_STATUS=$(curl -sS -o /dev/null -w '%{http_code}' -b /tmp/pk-cookie \
 --data-urlencode "pk_csrf_token=$TOKEN" --data-urlencode 'confirmed=yes' \
 --data-urlencode 'workflow_key=unauthorized_new_flow' \
 --data-urlencode 'name=Not Allowed' \
 --data-urlencode 'request_type=softcopy_cancel' \
 http://127.0.0.1:8089/admin/workflows/save)
test "$DENY_STATUS" = 404
test "$(db "SELECT COUNT(*) FROM workflows")" = "$WORKFLOWS_BEFORE"
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-wf-fixed.html \
 http://127.0.0.1:8089/admin/workflows
if grep -q 'New Workflow\|admin/workflows/save' /tmp/pk-wf-fixed.html; then
 echo 'Workflow creation UI must not be present.'
 exit 1
fi
# Only draft workflow steps can change. Published version remains intact.
ORIGINAL_VER=$(db "SELECT id FROM workflow_versions WHERE workflow_id=$WF_ID AND is_default=1")
post_form admin/workflows admin/workflows/clone "id=$WF_ID"
WF_VER=$(db "SELECT id FROM workflow_versions WHERE workflow_id=$WF_ID AND status='draft'")
test -n "$WF_VER"
post_form admin/workflows admin/workflows/step/remove "id=$WF_VER" 'step_key=step_1'
post_form admin/workflows admin/workflows/step/save \
 "workflow_version_id=$WF_VER" 'name=Final Administrative Decision' \
 'approver_type=role' "approver_role_id=$ADMIN_ROLE"
post_form admin/workflows admin/workflows/step/save \
 "workflow_version_id=$WF_VER" 'name=Recipient Initial Review' \
 'approver_type=user' "approver_user_id=$REC_ID"
post_form admin/workflows admin/workflows/step/move \
 "id=$WF_VER" 'step_key=step_2' 'direction=up'
test "$(db "SELECT JSON_UNQUOTE(JSON_EXTRACT(graph,'$.steps[0].approver.type'))
 FROM workflow_versions WHERE id=$WF_VER")" = user
test "$(db "SELECT JSON_UNQUOTE(JSON_EXTRACT(graph,'$.steps[0].approver.value'))
 FROM workflow_versions WHERE id=$WF_VER")" = "$REC_ID"

post_form admin/workflows admin/workflows/publish "id=$WF_VER"
test "$(db "SELECT status FROM workflow_versions WHERE id=$WF_VER")" = published
test "$(db "SELECT active FROM workflows WHERE id=$WF_ID")" = 1
test "$(db "SELECT is_default FROM workflow_versions WHERE id=$ORIGINAL_VER")" = 0
test "$(db "SELECT is_default FROM workflow_versions WHERE id=$WF_VER")" = 1
test "$(db "SELECT COUNT(*) FROM workflows")" = "$WORKFLOWS_BEFORE"

post_form admin/workflows admin/workflows/step/save \
 "workflow_version_id=$WF_VER" 'name=Cannot Edit Published' \
 'approver_type=role' "approver_role_id=$ADMIN_ROLE"
test "$(db "SELECT JSON_LENGTH(JSON_EXTRACT(graph,'$.steps'))
 FROM workflow_versions WHERE id=$WF_VER")" = 2

post_form my-requests/softcopy my-requests/softcopy/save \
 'type=softcopy_cancel' 'subject=Two stage cancellation' \
 "softcopy_id=$SOFT_ID" 'remarks=Approval routing test'
WF_REQUEST=$(db "SELECT id FROM requests WHERE type='softcopy_cancel' ORDER BY id DESC LIMIT 1")
test -n "$WF_REQUEST"
post_form my-requests/softcopy my-requests/softcopy/submit "id=$WF_REQUEST"
test "$(db "SELECT workflow_version_id FROM requests WHERE id=$WF_REQUEST")" = "$WF_VER"
test "$(db "SELECT assigned_user_id FROM workflow_steps
 WHERE request_id=$WF_REQUEST AND status='active'")" = "$REC_ID"
test "$(db "SELECT status FROM softcopy_documents WHERE id=$SOFT_ID")" = active

# Administrator is NOT assigned to stage one.
post_form my-tasks/softcopy my-tasks/softcopy/decide "id=$WF_REQUEST" 'decision=approved'
test "$(db "SELECT current_node FROM requests WHERE id=$WF_REQUEST")" = step_1

curl -fsS -b /tmp/pk-recipient-cookie -o /tmp/pk-approver-tasks.html \
 http://127.0.0.1:8089/my-tasks/softcopy
grep -q 'Recipient Initial Review' /tmp/pk-approver-tasks.html
APPROVER_TOKEN=$(perl -0777 -ne 'if (/name="pk_csrf_token"\s+value="([^"]+)"/) { print $1 }' \
 /tmp/pk-approver-tasks.html)
test -n "$APPROVER_TOKEN"
curl -fsS -b /tmp/pk-recipient-cookie -c /tmp/pk-recipient-cookie -o /dev/null \
 --data-urlencode "pk_csrf_token=$APPROVER_TOKEN" --data-urlencode 'confirmed=yes' \
 --data-urlencode "id=$WF_REQUEST" --data-urlencode 'decision=approved' \
 http://127.0.0.1:8089/my-tasks/softcopy/decide
test "$(db "SELECT current_node FROM requests WHERE id=$WF_REQUEST")" = step_2
test "$(db "SELECT status FROM softcopy_documents WHERE id=$SOFT_ID")" = active

post_form my-tasks/softcopy my-tasks/softcopy/decide "id=$WF_REQUEST" 'decision=approved'
test "$(db "SELECT status FROM requests WHERE id=$WF_REQUEST")" = completed
test "$(db "SELECT status FROM softcopy_documents WHERE id=$SOFT_ID")" = cancelled
test "$(db "SELECT COUNT(*) FROM workflow_steps
 WHERE request_id=$WF_REQUEST AND status='approved'")" = 2
test "$(db "SELECT COUNT(*) FROM workflow_history
 WHERE request_id=$WF_REQUEST AND action='approved'")" = 2

curl -fsS -b /tmp/pk-cookie -o /tmp/pk-approval-route.html \
 http://127.0.0.1:8089/my-requests/softcopy
grep -q 'Approval Route' /tmp/pk-approval-route.html
grep -q 'Recipient Initial Review' /tmp/pk-approval-route.html
grep -q 'Final Administrative Decision' /tmp/pk-approval-route.html
echo 'Sequential approval route, assigned-user decision, role decision and final effect passed.'
