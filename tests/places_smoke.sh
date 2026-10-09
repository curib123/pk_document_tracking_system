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

# Every Location upsert choice is a real row with its parent metadata.
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-location-page.html http://127.0.0.1:8089/places/location
grep -q 'data-location-upsert' /tmp/pk-location-page.html
grep -q 'name="archive_date"' /tmp/pk-location-page.html
grep -q "data-area-id=\"$AREA_ID\"" /tmp/pk-location-page.html
grep -q "data-specific-id=\"$SPEC_ID\"" /tmp/pk-location-page.html

# The server must infer correct parents even if the browser omits them.
post_form places/location places/location/save 'name=Asset-only Location' 'code=AUTO-ASSET' \
    "asset_id=$ASSET_ID" 'active=1'
test "$(db "SELECT CONCAT_WS(',',area_id,specific_id,asset_id)
    FROM locations WHERE code='AUTO-ASSET'")" = "$AREA_ID,$SPEC_ID,$ASSET_ID"

post_form places/location places/location/save 'name=Specific-only Location' 'code=AUTO-SPEC' \
    "specific_id=$SPEC_ID" 'active=1'
test "$(db "SELECT CONCAT_WS(',',area_id,specific_id)
    FROM locations WHERE code='AUTO-SPEC'")" = "$AREA_ID,$SPEC_ID"

# A mismatched parent, missing asset, or invalid archive date cannot be saved.
post_form places/area places/area/save 'name=Unrelated Wing' 'active=1'
OTHER_AREA_ID=$(db "SELECT id FROM areas WHERE name='Unrelated Wing'")
post_form places/location places/location/save 'name=Wrong Hierarchy' 'code=INVALID-PARENT' \
    "area_id=$OTHER_AREA_ID" "specific_id=$SPEC_ID" "asset_id=$ASSET_ID" 'active=1'
test "$(db "SELECT COUNT(*) FROM locations WHERE code='INVALID-PARENT'")" = 0

post_form places/location places/location/save 'name=Missing Asset' 'code=INVALID-ASSET' \
    'asset_id=999999999' 'active=1'
test "$(db "SELECT COUNT(*) FROM locations WHERE code='INVALID-ASSET'")" = 0

post_form places/location places/location/save 'name=Bad Archive Date' 'code=INVALID-DATE' \
    'archive_date=2026-02-30' 'active=1'
test "$(db "SELECT COUNT(*) FROM locations WHERE code='INVALID-DATE'")" = 0

# Existing location upsert retains the configured hierarchy and valid archive date.
post_form places/location places/location/save "id=$LOCATION_ID" 'name=Updated Archive Shelf' \
    'code=AS-1' "area_id=$AREA_ID" "specific_id=$SPEC_ID" "asset_id=$ASSET_ID" \
    'archive_date=2026-10-09' 'active=1'
test "$(db "SELECT name FROM locations WHERE id=$LOCATION_ID")" = 'Updated Archive Shelf'
test "$(db "SELECT archive_date FROM locations WHERE id=$LOCATION_ID")" = '2026-10-09'
test "$(db "SELECT CONCAT_WS(',',area_id,specific_id,asset_id)
    FROM locations WHERE id=$LOCATION_ID")" = "$AREA_ID,$SPEC_ID,$ASSET_ID"
echo 'Location Upsert hierarchy: predefined options, inferred parents, conflicts and edits passed.'

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
