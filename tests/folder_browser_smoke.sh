#!/usr/bin/env bash
# Existing pk_dts relational folder trees are navigable without a new schema.
set -euo pipefail
echo 'CASE: shared hardcopy/softcopy folder browser and clickable DataTable rows'

post_form places/area places/area/save 'name=Archive Block F' 'active=1'
BROWSE_AREA=$(db "SELECT id FROM areas WHERE name='Archive Block F'")
post_form places/specific places/specific/save 'name=Shelf Room F' \
    "area_id=$BROWSE_AREA" 'active=1'
BROWSE_SPEC=$(db "SELECT id FROM specifics WHERE name='Shelf Room F'")
post_form places/asset places/asset/save 'asset_number=BROWSE-CABINET-F' \
    "specific_id=$BROWSE_SPEC" 'active=1'
BROWSE_ASSET=$(db "SELECT id FROM assets WHERE asset_number='BROWSE-CABINET-F'")
post_form places/location places/location/save 'name=Secure Shelf F' \
    'code=ARCHIVE-F' "area_id=$BROWSE_AREA" "specific_id=$BROWSE_SPEC" \
    "asset_id=$BROWSE_ASSET" 'active=1'
BROWSE_LOC=$(db "SELECT id FROM locations WHERE code='ARCHIVE-F'")
test -n "$BROWSE_LOC"

post_form documents/hardcopy documents/hardcopy/save \
   'title=Folder Hardcopy Alpha' "location_id=$BROWSE_LOC" \
   "holder_id=$ADMIN_ID" 'retention_enabled=0'
BROWSE_DOC=$(db "SELECT id FROM hardcopy_documents WHERE title='Folder Hardcopy Alpha'")
test -n "$BROWSE_DOC"

# Tree navigation uses genuine predefined Places relations and folder-scoped SQL.
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-folders-root.html \
  http://127.0.0.1:8089/documents/hardcopy
grep -q 'Folder Explorer' /tmp/pk-folders-root.html
grep -q "folder=area%3A$BROWSE_AREA" /tmp/pk-folders-root.html
for path in "area:$BROWSE_AREA" "specific:$BROWSE_SPEC" \
    "asset:$BROWSE_ASSET" "location:$BROWSE_LOC"; do
    curl -fsS -b /tmp/pk-cookie -o /tmp/pk-folder-page.html \
      "http://127.0.0.1:8089/documents/hardcopy?folder=$path"
    grep -q 'Folder Explorer' /tmp/pk-folder-page.html
    grep -q 'Folder Hardcopy Alpha' /tmp/pk-folder-page.html
    grep -q 'data-row-view=' /tmp/pk-folder-page.html
    grep -q 'class="table-cell-view"' /tmp/pk-folder-page.html
    grep -q 'name="folder"' /tmp/pk-folder-page.html
done

post_form places/softcopy-categories places/softcopy-categories/save \
  'name=Engineering Parent' 'folder_name=engineering-parent' 'active=1'
PARENT_CAT=$(db "SELECT id FROM categories WHERE name='Engineering Parent'")
post_form places/softcopy-categories places/softcopy-categories/save \
  'name=Engineering Children' 'folder_name=engineering-children' \
  "parent_id=$PARENT_CAT" 'active=1'
CHILD_CAT=$(db "SELECT id FROM categories WHERE name='Engineering Children'")
test -n "$CHILD_CAT"
db "INSERT INTO softcopy_documents (document_number,title,category_id,
  created_by,creation_source,creation_reason)
  VALUES ('BROWSE-F-001','Folder Softcopy Beta',$CHILD_CAT,$ADMIN_ID,'direct','Folder test')"
BROWSE_SOFT=$(db "SELECT id FROM softcopy_documents WHERE document_number='BROWSE-F-001'")
test -n "$BROWSE_SOFT"

curl -fsS -b /tmp/pk-cookie -o /tmp/pk-category-root.html \
  http://127.0.0.1:8089/documents/softcopy
grep -q "folder=category%3A$PARENT_CAT" /tmp/pk-category-root.html
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-category-parent.html \
  "http://127.0.0.1:8089/documents/softcopy?folder=category:$PARENT_CAT"
grep -q 'Engineering Children' /tmp/pk-category-parent.html
grep -q "folder=category%3A$CHILD_CAT" /tmp/pk-category-parent.html
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-category-child.html \
  "http://127.0.0.1:8089/documents/softcopy?folder=category:$CHILD_CAT"
grep -q 'Folder Softcopy Beta' /tmp/pk-category-child.html
grep -q 'data-row-view=' /tmp/pk-category-child.html

# Bad, nonexistent and cross-domain folders must reject rather than leak data.
for bad in 'invalid:1' 'category:99999999' 'area:99999999'; do
  status=$(curl -sS -b /tmp/pk-cookie -o /dev/null -w '%{http_code}' \
    "http://127.0.0.1:8089/documents/softcopy?folder=$bad")
  test "$status" = 404
done

# Admin selection uses exactly the same path, including folder-specific
# document options and current assignments.
curl -fsS -b /tmp/pk-cookie -o /tmp/pk-admin-hard-folder.html \
  "http://127.0.0.1:8089/admin/document-assignments?domain=hardcopy&folder=location:$BROWSE_LOC"
grep -q 'Folder Hardcopy Alpha' /tmp/pk-admin-hard-folder.html
grep -q 'name="folder"' /tmp/pk-admin-hard-folder.html
grep -q 'id="assignmentModal"' /tmp/pk-admin-hard-folder.html
grep -q 'class="table-cell-view"' /tmp/pk-admin-hard-folder.html
grep -q 'data-row-view=' /tmp/pk-admin-hard-folder.html

# The save route rejects document IDs outside the opened location folder.
OTHER_HOLDER_BEFORE=$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$HARD_ID")
post_form admin/document-assignments admin/document-assignments/save \
  'document_domain=hardcopy' "folder=location:$BROWSE_LOC" \
  "hardcopy_id=$HARD_ID" "recipient_id=$ADMIN_ID"
test "$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$HARD_ID")" = "$OTHER_HOLDER_BEFORE"

post_form admin/document-assignments admin/document-assignments/save \
  'document_domain=hardcopy' "folder=location:$BROWSE_LOC" \
  "hardcopy_id=$BROWSE_DOC" "recipient_id=$REC_ID"
test "$(db "SELECT holder_id FROM hardcopy_documents WHERE id=$BROWSE_DOC")" = "$REC_ID"

curl -fsS -b /tmp/pk-cookie -o /tmp/pk-admin-soft-folder.html \
  "http://127.0.0.1:8089/admin/document-assignments?domain=softcopy&folder=category:$CHILD_CAT"
grep -q 'Folder Softcopy Beta' /tmp/pk-admin-soft-folder.html
grep -q 'name="softcopy_id"' /tmp/pk-admin-soft-folder.html
post_form admin/document-assignments admin/document-assignments/save \
  'document_domain=softcopy' "folder=category:$CHILD_CAT" \
  "softcopy_id=$BROWSE_SOFT" "recipient_id=$REC_ID"
test "$(db "SELECT COUNT(*) FROM assignments WHERE
 softcopy_id=$BROWSE_SOFT AND user_id=$REC_ID AND active=1")" = 1

echo 'Both folder hierarchies, server-side filtering, admin folder assignments and table-view interactions passed.'
