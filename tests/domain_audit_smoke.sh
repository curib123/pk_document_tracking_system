#!/usr/bin/env bash
# Runs in disposable MySQL fixture only.
set -euo pipefail
echo 'CASE: domain-aware access grants, assignment forms and JSON audit logs'
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-admin-assignment.html \
  http://127.0.0.1:8089/admin/document-assignments
grep -q 'Assign Documents' /tmp/pk-admin-assignment.html
grep -q 'name="document_domain"' /tmp/pk-admin-assignment.html
grep -q 'name="hardcopy_id"' /tmp/pk-admin-assignment.html
grep -q 'name="softcopy_id"' /tmp/pk-admin-assignment.html
grep -q 'data-record=' /tmp/pk-admin-assignment.html

# Administrator can assign a hardcopy without creating a workflow request.
ADMIN_ASSIGN_BEFORE=$(db 'SELECT COUNT(*) FROM requests')
post_form admin/document-assignments admin/document-assignments/save \
  'document_domain=hardcopy' "hardcopy_id=$WF_DOC" "recipient_id=$REC_ID"
test "$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$WF_DOC")" = "$REC_ID"
test "$(db 'SELECT COUNT(*) FROM requests')" = "$ADMIN_ASSIGN_BEFORE"

# Softcopy administrative assignment still uses the original assignments table.
post_form admin/document-assignments admin/document-assignments/save \
  'document_domain=softcopy' "softcopy_id=$SOFT_ID" "recipient_id=$REC_ID"
test "$(db "SELECT COUNT(*) FROM assignments
  WHERE softcopy_id=$SOFT_ID AND user_id=$REC_ID AND active=1")" = 1

# access_grants is polymorphic. Hardcopy access is supported without altering SQL.
post_form my-requests/access-grant my-requests/access-grant/save \
  'type=access' 'subject=Grant hardcopy access' 'document_domain=hardcopy' \
  "hardcopy_id=$WF_DOC" "recipient_id=$REC_ID" 'expires_at=2028-12-31'
HARD_GRANT_REQ=$(db "SELECT id FROM requests WHERE type='access' AND hardcopy_id=$WF_DOC ORDER BY id DESC LIMIT 1")
test -n "$HARD_GRANT_REQ"
post_form my-requests/access-grant my-requests/access-grant/submit "id=$HARD_GRANT_REQ"
post_form my-tasks/access-grant my-tasks/access-grant/decide "id=$HARD_GRANT_REQ" 'decision=approved'
test "$(db "SELECT domain FROM access_grants WHERE request_id=$HARD_GRANT_REQ")" = hardcopy

# Hardcopy assignment through workflow remains pending until approval.
post_form my-requests/document-assign my-requests/document-assign/save \
  'type=assignment' 'subject=Hardcopy assignment request' 'document_domain=hardcopy' \
  "hardcopy_id=$WF_DOC" "recipient_id=$ADMIN_ID"
ASSIGN_REQ=$(db "SELECT id FROM requests WHERE type='assignment'
  AND hardcopy_id=$WF_DOC ORDER BY id DESC LIMIT 1")
test -n "$ASSIGN_REQ"
test "$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$WF_DOC")" = "$REC_ID"
post_form my-requests/document-assign my-requests/document-assign/submit "id=$ASSIGN_REQ"
post_form my-tasks/document-assign my-tasks/document-assign/decide "id=$ASSIGN_REQ" 'decision=approved'
test "$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$WF_DOC")" = "$ADMIN_ID"

# Disposal reasons can only be enums; Other requires descriptive text.
post_form documents/hardcopy documents/hardcopy/dispose \
  "id=$WF_DOC" 'disposal_reason=other' 'disposal_other=Equipment retired'
test "$(db "SELECT disposal_action FROM disposals
  WHERE domain='hardcopy' AND document_id=$WF_DOC ORDER BY id DESC LIMIT 1")" = other
test "$(db "SELECT remarks FROM disposals
  WHERE domain='hardcopy' AND document_id=$WF_DOC ORDER BY id DESC LIMIT 1")" = 'Equipment retired'

# Request-based disposal stores typed method only after workflow approval.
post_form my-requests/hardcopy my-requests/hardcopy/save \
 'type=disposal' 'subject=Destroy obsolete hardcopy' \
 "hardcopy_id=$HARD_ID" 'disposal_reason=shred'
DIS_REQ=$(db "SELECT id FROM requests WHERE type='disposal' ORDER BY id DESC LIMIT 1")
test -n "$DIS_REQ"
post_form my-requests/hardcopy my-requests/hardcopy/submit "id=$DIS_REQ"
post_form my-tasks/hardcopy my-tasks/hardcopy/decide "id=$DIS_REQ" 'decision=approved'
test "$(db "SELECT disposal_action FROM disposals
  WHERE request_id=$DIS_REQ")" = shred

# Every successful form write adds an audit entry to the CURRENT Manila day JSON file.
day=$(TZ=Asia/Manila date +%Y-%m-%d)
audit="storage/audit/$day.json"
test -f "$audit"
php -r '
 $items=json_decode(file_get_contents($argv[1]),true);
 if (!is_array($items) || count($items)<5) exit(1);
 foreach ($items as $entry) {
  if (!isset($entry["timestamp"],$entry["actor_id"],$entry["action"]))
    exit(1);
  if (isset($entry["password"]) || isset($entry["password_hash"])) exit(1);
 }
' "$audit"
echo 'Hardcopy/softcopy grants, admin assignments, enum disposal and daily JSON audits passed.'
