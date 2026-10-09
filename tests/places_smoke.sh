#!/usr/bin/env bash
# Reuses disposable database and logged-in administrator from tests/smoke.sh.
set -euo pipefail

post_form places/area places/area/save 'name=Production Wing' 'active=1'
AREA_ID=$(db "SELECT id FROM areas WHERE name='Production Wing'")
test -n "$AREA_ID"

post_form places/specific places/specific/save 'name=Machine Room' "area_id=$AREA_ID" 'active=1'
SPEC_ID=$(db "SELECT id FROM specifics WHERE name='Machine Room'")
test -n "$SPEC_ID"

post_form places/asset places/asset/save 'asset_number=PK-MACHINE-01' "specific_id=$SPEC_ID" 'active=1'
ASSET_ID=$(db "SELECT id FROM assets WHERE asset_number='PK-MACHINE-01'")
test -n "$ASSET_ID"

post_form places/location places/location/save 'name=Archive Shelf' 'code=AS-1' \
    "area_id=$AREA_ID" "specific_id=$SPEC_ID" "asset_id=$ASSET_ID" 'active=1'
LOCATION_ID=$(db "SELECT id FROM locations WHERE code='AS-1'")
test -n "$LOCATION_ID"

post_form places/softcopy-categories places/softcopy-categories/save \
    'name=Operating Procedures' 'folder_name=ops-procedures' 'active=1'
test "$(db "SELECT COUNT(*) FROM categories WHERE name='Operating Procedures'")" = 1

post_form places/area places/area/deactivate "id=$AREA_ID"
test "$(db "SELECT active FROM areas WHERE id=$AREA_ID")" = 0

# The uploaded sequences table uses a string key, not an integer ID.
db "INSERT INTO sequences(sequence_key,value) VALUES ('PK-ARCHIVE', 12)"
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-sequences-page.html http://127.0.0.1:8089/places/sequence
grep -q 'PK-ARCHIVE' /tmp/pk-sequences-page.html
grep -q 'read-only' /tmp/pk-sequences-page.html
echo 'Original pk_dts Places CRUD passed on six-module relational schema.'
