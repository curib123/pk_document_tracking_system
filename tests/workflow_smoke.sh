#!/usr/bin/env bash
# Executed only by tests/smoke.sh against disposable pk_dts_test.
set -euo pipefail
trap 'echo "Integration assertion failed at workflow_smoke.sh:$LINENO"' ERR
db() { mysql -N -s -h127.0.0.1 -uroot -prootpass pk_dts_test -e "$1"; }
token_for() {
    curl -fsS -b /tmp/pk-cookie -c /tmp/pk-cookie \
      -o /tmp/pk-form.html "http://127.0.0.1:8089/$1"
    grep -o 'name="pk_csrf_token" value="[^"]*"' /tmp/pk-form.html |
      head -1 | sed 's/.*value="//;s/"$//'
}
post_form() {
    local page="$1" action="$2"; shift 2
    local t; t=$(token_for "$page")
    local args=()
    for item in "$@"; do args+=(--data-urlencode "$item"); done
    curl -fsS -b /tmp/pk-cookie -c /tmp/pk-cookie -o /dev/null \
      --data-urlencode "pk_csrf_token=$t" --data-urlencode 'confirmed=yes' \
      "${args[@]}" "http://127.0.0.1:8089/$action"
}

# All fixtures use the original pk_dts tables, not the previous invented ones.
ADMIN_ID=$(db "SELECT id FROM users WHERE username='test_admin'")
db "INSERT INTO categories(name,folder_name,created_by,active)
 VALUES ('Quality','quality',$ADMIN_ID,1);
 INSERT INTO locations(name,code,active) VALUES ('QA Archive','QA-A',1);
 INSERT INTO users(username,first_name,last_name,position_title,role_id,password_hash,require_password_change,active)
 SELECT 'recipient_test','Document','Recipient','Staff',id,'$HASH',0,1
 FROM roles WHERE name='Staff';"
CAT_ID=$(db "SELECT id FROM categories WHERE name='Quality'")
LOC_ID=$(db "SELECT id FROM locations WHERE name='QA Archive'")
REC_ID=$(db "SELECT id FROM users WHERE username='recipient_test'")
ADMIN_ROLE=$(db "SELECT id FROM roles WHERE name='Administrator'")

for t in softcopy_create softcopy_revise hardcopy_create transfer assignment access disposal softcopy_cancel hardcopy_update; do
    db "INSERT INTO workflows(workflow_key,name,request_type,active,created_by)
        VALUES ('qa_$t','QA $t','$t',1,$ADMIN_ID);
        INSERT INTO workflow_versions(workflow_id,version_number,status,is_default,graph,created_by,published_at)
        SELECT id,1,'published',1,
        '{\"steps\":[{\"key\":\"step_1\",\"name\":\"Admin Approval\",\"approver\":{\"type\":\"role\",\"value\":$ADMIN_ROLE,\"label\":\"Administrator\"}}]}',
        $ADMIN_ID,NOW() FROM workflows WHERE workflow_key='qa_$t';"
done

echo 'CASE: create softcopy'
# Create a softcopy through request approval.
post_form my-requests/softcopy my-requests/softcopy/save \
    'type=softcopy_create' 'subject=QA Softcopy Create' \
    'title=Control Checklist' 'document_number=QA-001' "category_id=$CAT_ID"
RID=$(db "SELECT id FROM requests WHERE type='softcopy_create' ORDER BY id DESC LIMIT 1")
test -n "$RID"
post_form my-requests/softcopy my-requests/softcopy/submit "id=$RID"
test "$(db "SELECT status FROM requests WHERE id=$RID")" = submitted
post_form my-tasks/softcopy my-tasks/softcopy/decide "id=$RID" 'decision=approved'
test "$(db "SELECT status FROM requests WHERE id=$RID")" = completed
SOFT_ID=$(db "SELECT id FROM softcopy_documents WHERE document_number='QA-001'")
test -n "$SOFT_ID"
test "$(db "SELECT COUNT(*) FROM workflow_history WHERE request_id=$RID")" -ge 2

echo 'CASE: direct private revision upload'
# Private files use the original files + softcopy_revisions tables, not a new file schema.
printf 'PK DTS private revision content\\n' > /tmp/pk-private-revision.txt
UPLOAD_TOKEN=$(token_for documents/softcopy)
curl -fsS -b /tmp/pk-cookie -c /tmp/pk-cookie -o /dev/null \
  -F "pk_csrf_token=$UPLOAD_TOKEN" -F 'confirmed=yes' \
  -F "document_id=$SOFT_ID" -F 'new_revision_level=REV-A' \
  -F 'page_number=1' -F 'attachment=@/tmp/pk-private-revision.txt;type=text/plain' \
  http://127.0.0.1:8089/files/upload
FILE_ID=$(db "SELECT id FROM files WHERE domain='softcopy' AND document_id=$SOFT_ID AND status='approved' ORDER BY id DESC LIMIT 1")
test -n "$FILE_ID"
REV_ID=$(db "SELECT current_revision_id FROM softcopy_documents WHERE id=$SOFT_ID")
test -n "$REV_ID"
test "$(db "SELECT file_id FROM softcopy_revisions WHERE id=$REV_ID")" = "$FILE_ID"
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-downloaded-revision.txt "http://127.0.0.1:8089/files/download/$FILE_ID"
cmp /tmp/pk-private-revision.txt /tmp/pk-downloaded-revision.txt

echo 'CASE: staged workflow revision'
# A requested revision stores a pending file first, then publishes that file
# only after the original versioned approval workflow completes.
printf 'Revision awaiting signed approval\n' > /tmp/pk-pending-revision.txt
REVISE_TOKEN=$(token_for my-requests/softcopy)
curl -fsS -b /tmp/pk-cookie -c /tmp/pk-cookie -o /dev/null \
  -F "pk_csrf_token=$REVISE_TOKEN" -F 'confirmed=yes' \
  -F 'type=softcopy_revise' -F 'subject=QA Revision Request' \
  -F "softcopy_id=$SOFT_ID" -F 'title=Control Checklist Revision' \
  -F 'new_revision_level=REV-B' -F 'effective_date=2026-10-09' \
  -F 'date_received=2026-10-08' -F 'date_released=2026-10-09' \
  -F 'page_number=2' \
  -F 'revision_attachment=@/tmp/pk-pending-revision.txt;type=text/plain' \
  http://127.0.0.1:8089/my-requests/softcopy/save
REV_REQUEST_ID=$(db "SELECT id FROM requests WHERE type='softcopy_revise' ORDER BY id DESC LIMIT 1")
test -n "$REV_REQUEST_ID"
PENDING_ID=$(db "SELECT id FROM files WHERE status='pending' AND document_id=$SOFT_ID ORDER BY id DESC LIMIT 1")
test -n "$PENDING_ID"
test "$(db "SELECT COUNT(*) FROM softcopy_revisions WHERE file_id=$PENDING_ID")" = 0
post_form my-requests/softcopy my-requests/softcopy/submit "id=$REV_REQUEST_ID"
post_form my-tasks/softcopy my-tasks/softcopy/decide "id=$REV_REQUEST_ID" 'decision=approved'
test "$(db "SELECT status FROM requests WHERE id=$REV_REQUEST_ID")" = completed
test "$(db "SELECT status FROM files WHERE id=$PENDING_ID")" = approved
test "$(db "SELECT COUNT(*) FROM softcopy_revisions WHERE file_id=$PENDING_ID AND new_revision_level='REV-B'")" = 1
test "$(db "SELECT current_revision_id FROM softcopy_documents WHERE id=$SOFT_ID")" != "$REV_ID"

echo 'CASE: create hardcopy'
# Create a hardcopy using the same versioned workflow mechanism.
post_form my-requests/hardcopy my-requests/hardcopy/save \
    'type=hardcopy_create' 'subject=QA Hardcopy Create' 'title=Physical Guide'
RID=$(db "SELECT id FROM requests WHERE type='hardcopy_create' ORDER BY id DESC LIMIT 1")
post_form my-requests/hardcopy my-requests/hardcopy/submit "id=$RID"
post_form my-tasks/hardcopy my-tasks/hardcopy/decide "id=$RID" 'decision=approved'
HARD_ID=$(db "SELECT id FROM hardcopy_documents WHERE title='Physical Guide'")
test -n "$HARD_ID"

echo 'CASE: assignment'
# Assignment is linked to softcopy_id; transfer is linked to hardcopy_id.
post_form my-requests/document-assign my-requests/document-assign/save \
    'type=assignment' 'subject=QA Assignment' "softcopy_id=$SOFT_ID" "recipient_id=$REC_ID"
RID=$(db "SELECT id FROM requests WHERE type='assignment' ORDER BY id DESC LIMIT 1")
post_form my-requests/document-assign my-requests/document-assign/submit "id=$RID"
post_form my-tasks/document-assign my-tasks/document-assign/decide "id=$RID" 'decision=approved'
test "$(db "SELECT COUNT(*) FROM assignments WHERE softcopy_id=$SOFT_ID AND user_id=$REC_ID")" = 1

echo 'CASE: access grant'
post_form my-requests/access-grant my-requests/access-grant/save \
    'type=access' 'subject=QA Access' "softcopy_id=$SOFT_ID" \
    "recipient_id=$REC_ID" 'expires_at=2028-12-30'
RID=$(db "SELECT id FROM requests WHERE type='access' ORDER BY id DESC LIMIT 1")
post_form my-requests/access-grant my-requests/access-grant/submit "id=$RID"
post_form my-tasks/access-grant my-tasks/access-grant/decide "id=$RID" 'decision=approved'
test "$(db "SELECT COUNT(*) FROM access_grants WHERE request_id=$RID AND status='access_granted'")" = 1

echo 'CASE: transfer approval'
post_form my-requests/hardcopy-transfer my-requests/hardcopy-transfer/save \
    'type=transfer' 'subject=QA Transfer' "hardcopy_id=$HARD_ID" \
    "recipient_id=$REC_ID" "destination_location_id=$LOC_ID"
RID=$(db "SELECT id FROM requests WHERE type='transfer' ORDER BY id DESC LIMIT 1")
post_form my-requests/hardcopy-transfer my-requests/hardcopy-transfer/submit "id=$RID"
post_form my-tasks/hardcopy-transfer my-tasks/hardcopy-transfer/decide "id=$RID" 'decision=approved'
test "$(db "SELECT status FROM requests WHERE id=$RID")" = approved
test "$(db "SELECT COUNT(*) FROM transfers WHERE request_id=$RID AND recipient_status='pending'")" = 1

echo 'CASE: physical transfer dispatch and acceptance'
TRANSFER_ID=$(db "SELECT id FROM transfers WHERE request_id=$RID")
test -n "$TRANSFER_ID"

# Holder dispatches; only the intended recipient can confirm the physical handoff.
post_form my-tasks/hardcopy-transfer my-tasks/hardcopy-transfer/dispatch "id=$TRANSFER_ID"
test "$(db "SELECT status FROM transfers WHERE id=$TRANSFER_ID")" = in_transit

curl -fsS -c /tmp/pk-recipient-cookie -o /tmp/pk-recipient-login.html http://127.0.0.1:8089/login
REC_TOKEN=$(grep -o 'name="pk_csrf_token" value="[^"]*"' /tmp/pk-recipient-login.html | head -1 | sed 's/.*value="//;s/"$//')
curl -sS -L -b /tmp/pk-recipient-cookie -c /tmp/pk-recipient-cookie \
    -o /tmp/pk-recipient-home.html --data-urlencode "pk_csrf_token=$REC_TOKEN" \
    --data-urlencode 'login=recipient_test' --data-urlencode 'password=TemporaryTestPassword2026!' \
    http://127.0.0.1:8089/login
grep -q 'Dashboard' /tmp/pk-recipient-home.html

# Recipient cannot access an approved file until a grant or assignment exists.
curl -fsS -b /tmp/pk-recipient-cookie -o /tmp/pk-transfer-task.html \
    http://127.0.0.1:8089/my-tasks/hardcopy-transfer
grep -q 'Accept Hardcopy' /tmp/pk-transfer-task.html
REC_TOKEN=$(grep -o 'name="pk_csrf_token" value="[^"]*"' /tmp/pk-transfer-task.html | head -1 | sed 's/.*value="//;s/"$//')
curl -fsS -b /tmp/pk-recipient-cookie -c /tmp/pk-recipient-cookie -o /dev/null \
    --data-urlencode "pk_csrf_token=$REC_TOKEN" --data-urlencode 'confirmed=yes' \
    --data-urlencode "id=$TRANSFER_ID" http://127.0.0.1:8089/my-tasks/hardcopy-transfer/accept
test "$(db "SELECT status FROM transfers WHERE id=$TRANSFER_ID")" = accepted
test "$(db "SELECT recipient_status FROM transfers WHERE id=$TRANSFER_ID")" = accepted
test "$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$HARD_ID")" = "$REC_ID"
test "$(db "SELECT location_id FROM hardcopy_documents WHERE id=$HARD_ID")" = "$LOC_ID"
test "$(db "SELECT status FROM requests WHERE id=$RID")" = completed
test "$(db "SELECT COUNT(*) FROM workflow_history WHERE request_id=$RID AND action='transfer_accepted'")" = 1


echo 'Schema-aware request effects passed: softcopy, hardcopy, assignment, access and transfer.'
