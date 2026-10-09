#!/usr/bin/env bash
# Sourced at the end of integration_smoke.sh. Uses disposable pk_dts_test only.
set -euo pipefail
BASE='http://127.0.0.1:8081'
db() { mysql -N -s -h127.0.0.1 -uroot -prootpass pk_dts_test -e "$1"; }
csrf_for() {
    local cookie="$1" route="$2"
    curl -fsS -b "$cookie" -c "$cookie" -o /tmp/pk-form.html "$BASE/$route"
    token /tmp/pk-form.html
}
save_request() {
    local type="$1" subject="$2"; shift 2
    local t
    t=$(csrf_for /tmp/pk-staff.cookies "my-requests/$type")
    local args=()
    for param in "$@"; do args+=(--data-urlencode "$param"); done
    curl -sS -b /tmp/pk-staff.cookies -c /tmp/pk-staff.cookies -o /dev/null \
        --data-urlencode "pk_csrf_token=$t" --data-urlencode 'confirmed=yes' \
        --data-urlencode "subject=$subject" "${args[@]}" "$BASE/my-requests/$type/save"
    local id
    id=$(db "SELECT id FROM requests WHERE subject='$subject' LIMIT 1")
    test -n "$id"
    echo "$id"
}
submit_and_approve() {
    local type="$1" id="$2" t
    t=$(csrf_for /tmp/pk-staff.cookies "my-requests/$type")
    curl -sS -b /tmp/pk-staff.cookies -c /tmp/pk-staff.cookies -o /dev/null \
        --data-urlencode "pk_csrf_token=$t" --data-urlencode 'confirmed=yes' \
        --data-urlencode "id=$id" "$BASE/my-requests/$type/submit"
    test "$(db "SELECT status FROM requests WHERE id=$id")" = pending
    t=$(csrf_for /tmp/pk-manager.cookies "my-tasks/$type")
    grep -q "$type" /tmp/pk-form.html || true
    curl -sS -b /tmp/pk-manager.cookies -c /tmp/pk-manager.cookies -o /dev/null \
        --data-urlencode "pk_csrf_token=$t" --data-urlencode 'confirmed=yes' \
        --data-urlencode "id=$id" --data-urlencode 'decision=approved' \
        "$BASE/my-tasks/$type/decide"
    test "$(db "SELECT status FROM requests WHERE id=$id")" = approved
}
admin_post() {
    local page="$1" action="$2"; shift 2
    local t
    t=$(csrf_for /tmp/pk-admin.cookies "$page")
    local args=()
    for param in "$@"; do args+=(--data-urlencode "$param"); done
    curl -sS -b /tmp/pk-admin.cookies -c /tmp/pk-admin.cookies -o /dev/null \
        --data-urlencode "pk_csrf_token=$t" --data-urlencode 'confirmed=yes' \
        "${args[@]}" "$BASE/$action"
}

# Create a second employee and a reference location.
db "INSERT INTO users (role_id,name,username,email,password_hash)
 SELECT id,'Document Recipient','pk_recipient','pk_recipient@example.test','$HASH'
 FROM roles WHERE name='staff';"
RECIPIENT_ID=$(db "SELECT id FROM users WHERE username='pk_recipient'")
admin_post 'places/location' 'places/location/save' 'name=Quality Archives' 'active=1'
LOCATION_ID=$(db "SELECT id FROM places WHERE name='Quality Archives' AND type='location'")
test -n "$LOCATION_ID"
admin_post 'documents/hardcopy' 'documents/hardcopy/save' \
    'code=HC-100' 'title=Controlled Original' 'version=1' 'status=active'
HARDCOPY_ID=$(db "SELECT id FROM documents WHERE code='HC-100' AND kind='hardcopy'")
SOFTCOPY_ID=$(db "SELECT id FROM documents WHERE code='QA-2026' AND kind='softcopy'")
test -n "$HARDCOPY_ID"
test -n "$SOFTCOPY_ID"

# Store a real private file, verify the admin can download its exact content.
printf 'Private document for PK control testing\n' > /tmp/pk-version.txt
UPLOAD_CSRF=$(csrf_for /tmp/pk-admin.cookies documents/softcopy)
curl -sS -b /tmp/pk-admin.cookies -c /tmp/pk-admin.cookies -o /dev/null \
    -F "pk_csrf_token=$UPLOAD_CSRF" -F 'confirmed=yes' \
    -F "document_id=$SOFTCOPY_ID" -F 'attachment=@/tmp/pk-version.txt;type=text/plain' \
    "$BASE/documents/softcopy/upload"
FILE_ID=$(db "SELECT MAX(id) FROM document_files WHERE document_id=$SOFTCOPY_ID")
test "$FILE_ID" != NULL
curl -fsS -b /tmp/pk-admin.cookies -o /tmp/pk-download.txt "$BASE/documents/softcopy/files/$FILE_ID"
cmp /tmp/pk-version.txt /tmp/pk-download.txt
curl -fsS -b /tmp/pk-admin.cookies -o /tmp/pk-files-page.html "$BASE/documents/softcopy"
grep -q 'File Version History' /tmp/pk-files-page.html

# A user who is not an owner, assignee or grantee must not download private files.
RECIPIENT_CSRF=$(csrf_for /tmp/pk-recipient.cookies login)
curl -sS -L -b /tmp/pk-recipient.cookies -c /tmp/pk-recipient.cookies \
    -o /tmp/pk-recipient-home.html \
    --data-urlencode "pk_csrf_token=$RECIPIENT_CSRF" \
    --data-urlencode 'login=pk_recipient' \
    --data-urlencode 'password=TemporaryTestPassword!2026' "$BASE/login"
grep -q 'Dashboard' /tmp/pk-recipient-home.html
CODE=$(curl -sS -b /tmp/pk-recipient.cookies -o /dev/null -w '%{http_code}' \
    "$BASE/documents/softcopy/files/$FILE_ID")
test "$CODE" = 403

# Each new request type has a valid one-step workflow assigned to the manager.
db "INSERT INTO workflows (request_type,name,version,is_default,active) VALUES
 ('hardcopy','Hardcopy Review',1,1,1),
 ('hardcopy-transfer','Transfer Review',1,1,1),
 ('access-grant','Access Review',1,1,1),
 ('document-assign','Assignment Review',1,1,1);
 INSERT INTO workflow_steps (workflow_id,step_order,label,approver_type,approver_user_id)
 SELECT w.id,1,'Manager Review','user',u.id
 FROM workflows w CROSS JOIN users u
 WHERE w.request_type <> 'softcopy' AND u.username='pk_test_manager';"

# Revision affects the actual document, not just a request status.
ID=$(save_request softcopy 'CI revision' 'operation=revise' \
    "document_id=$SOFTCOPY_ID" 'proposed_code=QA-2026' \
    'proposed_title=Revised QA Guide' 'proposed_version=2')
submit_and_approve softcopy "$ID"
test "$(db "SELECT version FROM documents WHERE id=$SOFTCOPY_ID")" = 2
test "$(db "SELECT title FROM documents WHERE id=$SOFTCOPY_ID")" = 'Revised QA Guide'

# Hardcopy transfer updates the location and retains an audit row.
ID=$(save_request hardcopy-transfer 'CI transfer' \
    "document_id=$HARDCOPY_ID" "target_place_id=$LOCATION_ID")
submit_and_approve hardcopy-transfer "$ID"
test "$(db "SELECT place_id FROM documents WHERE id=$HARDCOPY_ID")" = "$LOCATION_ID"
test "$(db "SELECT COUNT(*) FROM document_transfers WHERE request_id=$ID")" = 1

# Access-grant approval makes only the selected private file available to its target.
ID=$(save_request access-grant 'CI access' \
    "document_id=$SOFTCOPY_ID" "target_user_id=$RECIPIENT_ID")
submit_and_approve access-grant "$ID"
test "$(db "SELECT COUNT(*) FROM document_access_grants WHERE request_id=$ID")" = 1
curl -fsS -b /tmp/pk-recipient.cookies -o /tmp/pk-granted-download.txt \
    "$BASE/documents/softcopy/files/$FILE_ID"
cmp /tmp/pk-version.txt /tmp/pk-granted-download.txt

# Revoke an approved grant through an authorized native form.
GRANT_ID=$(db "SELECT id FROM document_access_grants WHERE request_id=$ID")
admin_post 'documents/softcopy' 'documents/softcopy/grant/revoke' "id=$GRANT_ID"
test "$(db "SELECT COUNT(*) FROM document_access_grants WHERE id=$GRANT_ID AND revoked_at IS NOT NULL")" = 1
CODE=$(curl -sS -b /tmp/pk-recipient.cookies -o /dev/null -w '%{http_code}' \
    "$BASE/documents/softcopy/files/$FILE_ID")
test "$CODE" = 403

# Assignment changes the responsible user with a request reference.
ID=$(save_request document-assign 'CI assignment' \
    "document_id=$SOFTCOPY_ID" "target_user_id=$RECIPIENT_ID")
submit_and_approve document-assign "$ID"
test "$(db "SELECT user_id FROM document_assignments WHERE document_id=$SOFTCOPY_ID")" = "$RECIPIENT_ID"
curl -fsS -b /tmp/pk-recipient.cookies -o /tmp/pk-assigned-download.txt \
    "$BASE/documents/softcopy/files/$FILE_ID"
cmp /tmp/pk-version.txt /tmp/pk-assigned-download.txt

# A document-disposal request has a real lifecycle effect.
ID=$(save_request hardcopy 'CI dispose' 'operation=dispose' "document_id=$HARDCOPY_ID")
submit_and_approve hardcopy "$ID"
test "$(db "SELECT status FROM documents WHERE id=$HARDCOPY_ID")" = disposed

# Clone the in-use softcopy workflow; the new version retains its ordered steps.
WORKFLOW_ID=$(db "SELECT id FROM workflows WHERE request_type='softcopy' AND version=1")
admin_post 'admin/workflows' 'admin/workflows/clone' "id=$WORKFLOW_ID"
CLONED_ID=$(db "SELECT id FROM workflows WHERE request_type='softcopy' AND version=2")
test -n "$CLONED_ID"
test "$(db "SELECT COUNT(*) FROM workflow_steps WHERE workflow_id=$CLONED_ID")" = 1
test "$(db "SELECT is_default FROM workflows WHERE id=$CLONED_ID")" = 0
# Returned requests can be corrected and resubmitted without losing decision history.
ID=$(save_request softcopy 'CI returned request' 'operation=create' \
    'proposed_code=RET-001' 'proposed_title=To Be Corrected' 'proposed_version=1')
T=$(csrf_for /tmp/pk-staff.cookies my-requests/softcopy)
curl -sS -b /tmp/pk-staff.cookies -c /tmp/pk-staff.cookies -o /dev/null \
    --data-urlencode "pk_csrf_token=$T" --data-urlencode 'confirmed=yes' \
    --data-urlencode "id=$ID" "$BASE/my-requests/softcopy/submit"
T=$(csrf_for /tmp/pk-manager.cookies my-tasks/softcopy)
curl -sS -b /tmp/pk-manager.cookies -c /tmp/pk-manager.cookies -o /dev/null \
    --data-urlencode "pk_csrf_token=$T" --data-urlencode 'confirmed=yes' \
    --data-urlencode "id=$ID" --data-urlencode 'decision=returned' \
    "$BASE/my-tasks/softcopy/decide"
test "$(db "SELECT status FROM requests WHERE id=$ID")" = returned
T=$(csrf_for /tmp/pk-staff.cookies my-requests/softcopy)
curl -sS -b /tmp/pk-staff.cookies -c /tmp/pk-staff.cookies -o /dev/null \
    --data-urlencode "pk_csrf_token=$T" --data-urlencode 'confirmed=yes' \
    --data-urlencode "id=$ID" --data-urlencode 'subject=CI returned request' \
    --data-urlencode 'operation=create' --data-urlencode 'proposed_code=RET-002' \
    --data-urlencode 'proposed_title=Corrected Document' \
    --data-urlencode 'proposed_version=1' \
    "$BASE/my-requests/softcopy/save"
submit_and_approve softcopy "$ID"
test "$(db "SELECT COUNT(*) FROM request_decisions WHERE request_id=$ID")" = 2
test "$(db "SELECT COUNT(*) FROM documents WHERE code='RET-002'")" = 1

# Finalized requests cannot be approved a second time.
T=$(csrf_for /tmp/pk-manager.cookies my-tasks/softcopy)
curl -sS -b /tmp/pk-manager.cookies -c /tmp/pk-manager.cookies -o /dev/null \
    --data-urlencode "pk_csrf_token=$T" --data-urlencode 'confirmed=yes' \
    --data-urlencode "id=$ID" --data-urlencode 'decision=approved' \
    "$BASE/my-tasks/softcopy/decide"
test "$(db "SELECT COUNT(*) FROM request_decisions WHERE request_id=$ID")" = 2

# Last super-admin must not be deactivated by a crafted user save form.
ADMIN_ID=$(db "SELECT id FROM users WHERE username='pk_test_admin'")
ADMIN_ROLE=$(db "SELECT id FROM roles WHERE name='super_admin'")
admin_post 'admin/users' 'admin/users/save' "id=$ADMIN_ID" \
    'name=Test Admin' 'username=pk_test_admin' 'email=pk_admin@example.test' \
    "role_id=$ADMIN_ROLE" 'active=0'
test "$(db "SELECT active FROM users WHERE id=$ADMIN_ID")" = 1
echo 'Extended MySQL smoke passed: uploads, protected downloads, document lifecycle, grants/revoke, assignments, return/retry, duplicate-approval protection and workflow versioning.'

