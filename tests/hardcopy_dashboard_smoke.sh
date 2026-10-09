#!/usr/bin/env bash
# Disposable pk_dts_test only. Depends on shared db/post_form/token_for helpers.
set -euo pipefail

echo 'CASE: personal dashboard greeting'
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-welcome.html http://127.0.0.1:8089/dashboard
grep -Eq 'Good (morning|afternoon|evening),' /tmp/pk-welcome.html
grep -q 'dashboardGreeting' /tmp/pk-welcome.html
grep -q '<span>Test</span>' /tmp/pk-welcome.html
grep -q 'PK Document Control Workspace' /tmp/pk-welcome.html
if grep -q '<span class="eyebrow">Overview</span>' /tmp/pk-welcome.html; then
    echo 'Old generic Dashboard heading remains.'
    exit 1
fi

echo 'CASE: hardcopy predefined selection and administrator holder selection'
post_form places/area places/area/save 'name=QA Hardcopy Area' 'active=1'
HC_AREA=$(db "SELECT id FROM areas WHERE name='QA Hardcopy Area'")
post_form places/specific places/specific/save 'name=QA Cabinet' "area_id=$HC_AREA" 'active=1'
HC_SPEC=$(db "SELECT id FROM specifics WHERE name='QA Cabinet'")
post_form places/asset places/asset/save 'asset_number=QA-CABINET-001' "specific_id=$HC_SPEC" 'active=1'
HC_ASSET=$(db "SELECT id FROM assets WHERE asset_number='QA-CABINET-001'")
post_form places/location places/location/save 'name=QA Hardcopy Shelf' 'code=HC-QA-001' \
    "area_id=$HC_AREA" "specific_id=$HC_SPEC" "asset_id=$HC_ASSET" 'active=1'
HC_LOC=$(db "SELECT id FROM locations WHERE code='HC-QA-001'")
test -n "$HC_LOC"

curl -fsS -b /tmp/pk-cookie -o /tmp/pk-hardcopy-admin.html http://127.0.0.1:8089/documents/hardcopy
grep -q 'data-hardcopy-upsert' /tmp/pk-hardcopy-admin.html
grep -q 'name="holder_id"' /tmp/pk-hardcopy-admin.html
grep -q "data-specific-id=\"$HC_SPEC\"" /tmp/pk-hardcopy-admin.html
grep -q "data-area-id=\"$HC_AREA\"" /tmp/pk-hardcopy-admin.html
grep -q "data-asset-id=\"$HC_ASSET\"" /tmp/pk-hardcopy-admin.html
grep -q 'Administrators may assign a different active holder' /tmp/pk-hardcopy-admin.html
grep -q 'data-hardcopy-retention hidden' /tmp/pk-hardcopy-admin.html
grep -q 'name="retention_enabled"' /tmp/pk-hardcopy-admin.html

ADMIN_ID=$(db "SELECT id FROM users WHERE username='test_admin'")
post_form documents/hardcopy documents/hardcopy/save \
    'title=Admin Custody Document' "location_id=$HC_LOC" 'sequence_number=HC-001' \
    "holder_id=$REC_ID" 'retention_enabled=1' \
    'retention_start_date=2026-10-01' 'retention_end_date=2027-10-01'
HC_ID=$(db "SELECT id FROM hardcopy_documents WHERE title='Admin Custody Document'")
test -n "$HC_ID"
test "$(db "SELECT CONCAT_WS(',',holder_id,area_id,specific_id,asset_id,location_id)
    FROM hardcopy_documents WHERE id=$HC_ID")" = "$REC_ID,$HC_AREA,$HC_SPEC,$HC_ASSET,$HC_LOC"
test "$(db "SELECT retention_enabled FROM hardcopy_documents WHERE id=$HC_ID")" = 1

# A stale/foreign hierarchy is rejected and produces no record.
post_form documents/hardcopy documents/hardcopy/save \
    'title=Bad Custody Hierarchy' "location_id=$HC_LOC" 'area_id=999999' "holder_id=$ADMIN_ID"
test "$(db "SELECT COUNT(*) FROM hardcopy_documents WHERE title='Bad Custody Hierarchy'")" = 0

# One location cannot belong to two documents under original pk_dts unique index.
post_form documents/hardcopy documents/hardcopy/save \
    'title=Duplicate Location' "location_id=$HC_LOC" "holder_id=$ADMIN_ID"
test "$(db "SELECT COUNT(*) FROM hardcopy_documents WHERE title='Duplicate Location'")" = 0

echo 'CASE: non-admin current holder enforced by CI session'
# The uploaded role permissions give Document Control Officer hardcopy.direct.
db "INSERT INTO users(username,first_name,last_name,position_title,role_id,password_hash,require_password_change,active)
SELECT 'qa_officer','QA','Officer','Document Control',id,'$HASH',0,1
FROM roles WHERE name='Document Control Officer'"
OFFICER_ID=$(db "SELECT id FROM users WHERE username='qa_officer'")
test -n "$OFFICER_ID"
curl -fsS -c /tmp/pk-officer-cookie -o /tmp/pk-officer-login.html http://127.0.0.1:8089/login
officer_token=$(perl -0777 -ne 'if (/name="pk_csrf_token"\s+value="([^"]+)"/) { print $1 }' /tmp/pk-officer-login.html)
curl -fsS -L -b /tmp/pk-officer-cookie -c /tmp/pk-officer-cookie \
    -o /tmp/pk-officer-home.html --data-urlencode "pk_csrf_token=$officer_token" \
    --data-urlencode 'login=qa_officer' \
    --data-urlencode 'password=TemporaryTestPassword2026!' \
    http://127.0.0.1:8089/login
grep -q 'dashboardGreeting' /tmp/pk-officer-home.html
curl -fsS -b /tmp/pk-officer-cookie -o /tmp/pk-hardcopy-officer.html http://127.0.0.1:8089/documents/hardcopy
grep -q 'name="holder_name"' /tmp/pk-hardcopy-officer.html
grep -q 'value="QA Officer" readonly' /tmp/pk-hardcopy-officer.html
if grep -q '<select class="form-select" id="hardcopyHolder"' /tmp/pk-hardcopy-officer.html; then
    echo 'Holder selection editable for non-Administrator.'
    exit 1
fi

officer_post() {
    local token
    token=$(curl -fsS -b /tmp/pk-officer-cookie -c /tmp/pk-officer-cookie \
        http://127.0.0.1:8089/documents/hardcopy | \
        perl -0777 -ne 'if (/name="pk_csrf_token"\s+value="([^"]+)"/) { print $1 }')
    local args=()
    for item in "$@"; do args+=(--data-urlencode "$item"); done
    curl -fsS -b /tmp/pk-officer-cookie -c /tmp/pk-officer-cookie \
        -o /dev/null --data-urlencode "pk_csrf_token=$token" \
        --data-urlencode 'confirmed=yes' "${args[@]}" \
        http://127.0.0.1:8089/documents/hardcopy/save
}

officer_post 'title=Nonadmin Custody Document' 'sequence_number=HC-OFFICER-1' \
    "holder_id=$ADMIN_ID" 'retention_enabled=0'
OWN_DOC=$(db "SELECT id FROM hardcopy_documents WHERE title='Nonadmin Custody Document'")
test -n "$OWN_DOC"
test "$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$OWN_DOC")" = "$OFFICER_ID"

officer_post "id=$OWN_DOC" 'title=Officer Updated Document' "holder_id=$REC_ID"
test "$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$OWN_DOC")" = "$OFFICER_ID"

# Editing another holder's document must not silently transfer its custody.
officer_post "id=$HC_ID" 'title=Unauthorized Custody Change' "holder_id=$OFFICER_ID"
test "$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$HC_ID")" = "$REC_ID"
test "$(db "SELECT title FROM hardcopy_documents WHERE id=$HC_ID")" = 'Admin Custody Document'
echo 'Hardcopy current-holder and predefined hierarchy checks passed.'
