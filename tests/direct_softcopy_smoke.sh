#!/usr/bin/env bash
# Native, permission-guarded Softcopy Direct uses the same fields and domain
# effects as the request form, but does NOT create a workflow or request.
set -euo pipefail

echo 'CASE: direct softcopy create/revise/cancel'
before_requests=$(db "SELECT COUNT(*) FROM requests")
before_steps=$(db "SELECT COUNT(*) FROM workflow_steps")
before_history=$(db "SELECT COUNT(*) FROM workflow_history")

curl -fsS -b /tmp/pk-cookie -o /tmp/pk-direct-form.html http://127.0.0.1:8089/documents/softcopy
grep -q 'action="http://127.0.0.1:8089/documents/softcopy/direct"' /tmp/pk-direct-form.html
for field in type subject softcopy_id title document_number series_number \
    category_id new_revision_level page_number effective_date date_received \
    date_released revision_attachment remarks; do
    grep -q "name=\"$field\"" /tmp/pk-direct-form.html
done
grep -q 'Approve Directly' /tmp/pk-direct-form.html

post_form documents/softcopy documents/softcopy/direct \
    'type=softcopy_create' 'subject=Direct quality record' \
    'title=Direct Control Checklist' 'document_number=DIRECT-QA-001' \
    'series_number=SER-2026' "category_id=$CAT_ID" 'remarks=Immediate authorized creation'
DIRECT_ID=$(db "SELECT id FROM softcopy_documents WHERE document_number='DIRECT-QA-001'")
test -n "$DIRECT_ID"
test "$(db "SELECT creation_source FROM softcopy_documents WHERE id=$DIRECT_ID")" = direct
test "$(db "SELECT series_number FROM softcopy_documents WHERE id=$DIRECT_ID")" = SER-2026
test "$(db "SELECT status FROM softcopy_documents WHERE id=$DIRECT_ID")" = active
test "$(db "SELECT COUNT(*) FROM status_history WHERE domain='softcopy'
  AND document_id=$DIRECT_ID AND action='direct_create'")" = 1
test "$(db "SELECT COUNT(*) FROM requests")" = "$before_requests"

printf 'Direct softcopy revision payload\n' > /tmp/pk-direct-file.txt
direct_token=$(token_for documents/softcopy)
curl -fsS -b /tmp/pk-cookie -c /tmp/pk-cookie -o /dev/null \
    -F "pk_csrf_token=$direct_token" -F 'confirmed=yes' \
    -F 'type=softcopy_revise' -F 'subject=Direct revision' \
    -F "softcopy_id=$DIRECT_ID" -F 'title=Direct Control Checklist Revised' \
    -F 'new_revision_level=REV-1' -F 'effective_date=2026-10-09' \
    -F 'date_received=2026-10-08' -F 'date_released=2026-10-09' \
    -F 'page_number=2' \
    -F 'revision_attachment=@/tmp/pk-direct-file.txt;type=text/plain' \
    http://127.0.0.1:8089/documents/softcopy/direct
DIRECT_REV=$(db "SELECT current_revision_id FROM softcopy_documents WHERE id=$DIRECT_ID")
test -n "$DIRECT_REV"
test "$(db "SELECT new_revision_level FROM softcopy_revisions WHERE id=$DIRECT_REV")" = REV-1
test "$(db "SELECT page_number FROM softcopy_revisions WHERE id=$DIRECT_REV")" = 2
test "$(db "SELECT document_title FROM softcopy_revisions WHERE id=$DIRECT_REV")" = 'Direct Control Checklist Revised'
DIRECT_FILE=$(db "SELECT file_id FROM softcopy_revisions WHERE id=$DIRECT_REV")
test "$(db "SELECT status FROM files WHERE id=$DIRECT_FILE")" = approved
test "$(db "SELECT COUNT(*) FROM status_history WHERE domain='softcopy'
  AND document_id=$DIRECT_ID AND action='direct_revise'")" = 1
test "$(db "SELECT COUNT(*) FROM requests")" = "$before_requests"

# Invalid/missing revision attachment must not change the approved revision.
post_form documents/softcopy documents/softcopy/direct \
    'type=softcopy_revise' 'subject=Reject missing upload' \
    "softcopy_id=$DIRECT_ID" 'title=Should Not Change' \
    'new_revision_level=REV-2' 'effective_date=2026-10-09' \
    'date_received=2026-10-08' 'date_released=2026-10-09' 'page_number=1'
test "$(db "SELECT current_revision_id FROM softcopy_documents WHERE id=$DIRECT_ID")" = "$DIRECT_REV"

# Existing user with softcopy.view but without softcopy.direct cannot bypass the workflow.
curl -fsS -b /tmp/pk-recipient-cookie -c /tmp/pk-recipient-cookie \
    -o /tmp/pk-staff-direct-form.html http://127.0.0.1:8089/documents/softcopy
staff_token=$(perl -0777 -ne 'if (/name="pk_csrf_token"\s+value="([^"]+)"/) { print $1 }' \
    /tmp/pk-staff-direct-form.html)
test -n "$staff_token"
staff_status=$(curl -sS -b /tmp/pk-recipient-cookie -c /tmp/pk-recipient-cookie \
    -o /tmp/pk-staff-direct-response.html -w '%{http_code}' \
    --data-urlencode "pk_csrf_token=$staff_token" --data-urlencode 'confirmed=yes' \
    --data-urlencode 'type=softcopy_create' --data-urlencode 'subject=Unauthorized direct' \
    --data-urlencode 'title=Unauthorized' --data-urlencode 'document_number=DIRECT-DENIED' \
    --data-urlencode "category_id=$CAT_ID" \
    http://127.0.0.1:8089/documents/softcopy/direct)
test "$staff_status" = 403
test "$(db "SELECT COUNT(*) FROM softcopy_documents WHERE document_number='DIRECT-DENIED'")" = 0

post_form documents/softcopy documents/softcopy/direct \
    'type=softcopy_cancel' 'subject=Direct cancellation' \
    "softcopy_id=$DIRECT_ID" 'remarks=Cancelled by authorized document controller'
test "$(db "SELECT status FROM softcopy_documents WHERE id=$DIRECT_ID")" = cancelled
test "$(db "SELECT previous_status FROM softcopy_documents WHERE id=$DIRECT_ID")" = active
test "$(db "SELECT COUNT(*) FROM status_history WHERE domain='softcopy'
    AND document_id=$DIRECT_ID AND action='direct_cancel'")" = 1
test "$(db "SELECT COUNT(*) FROM requests")" = "$before_requests"
test "$(db "SELECT COUNT(*) FROM workflow_steps")" = "$before_steps"
test "$(db "SELECT COUNT(*) FROM workflow_history")" = "$before_history"

# Cancelling a document revokes its ability to be downloaded.
download_status=$(curl -sS -b /tmp/pk-cookie -o /dev/null -w '%{http_code}' \
    "http://127.0.0.1:8089/files/download/$DIRECT_FILE")
test "$download_status" = 403
echo 'Softcopy Direct approved immediately, with revision/file auditing and no workflow rows.'
