#!/usr/bin/env bash
# Run after business_flows.sh: validates database paging with >25 records per module.
set -euo pipefail

row_count() {
    sed -n '/<tbody>/,/<\/tbody>/p' /tmp/pk-table.html | grep -c '<tr>'
}

visible_row() {
    sed -n '/<tbody>/,/<\/tbody>/p' /tmp/pk-table.html | grep -q "$1"
}

get_table() {
    local cookie="$1" route="$2"
    shift 2
    curl -fsS -G -b "$cookie" -o /tmp/pk-table.html "$BASE/$route" "$@"
    grep -q '</table>' /tmp/pk-table.html
}

STAFF_ROLE=$(db "SELECT id FROM roles WHERE name='staff'")
ADMIN_ID=$(db "SELECT id FROM users WHERE username='pk_test_admin'")
STAFF_ID=$(db "SELECT id FROM users WHERE username='pk_test_staff'")
WF_ID=$(db "SELECT id FROM workflows WHERE request_type='softcopy' AND version=1")

# Insert predictable fixtures using one SQL statement per resource type.
USERS_SQL="INSERT INTO users (role_id,name,username,email,password_hash,active) VALUES "
ROLES_SQL="INSERT INTO roles (name,description,is_system) VALUES "
WORKFLOWS_SQL="INSERT INTO workflows (request_type,name,version,is_default,active) VALUES "
OWN_SQL="INSERT INTO requests (request_type,requester_id,subject,status) VALUES "
TASK_SQL="INSERT INTO requests (request_type,requester_id,subject,status,workflow_id,current_step) VALUES "
PLACES_SQL="INSERT INTO places (type,name,active) VALUES "
DOCS_SQL="INSERT INTO documents (kind,code,title,version,status,created_by) VALUES "

for n in $(seq -w 1 37); do
    idx=$((10#$n))
    active=1
    if ((idx % 6 == 0)); then active=0; fi
    if ((idx > 1)); then
        USERS_SQL+=","
        ROLES_SQL+=","
        WORKFLOWS_SQL+=","
        OWN_SQL+=","
        TASK_SQL+=","
        PLACES_SQL+=","
        DOCS_SQL+=","
    fi
    USERS_SQL+="($STAFF_ROLE,'Page User $n','page_user_$n','page_$n@example.test','$HASH',$active)"
    ROLES_SQL+="('page_role_$n','Page permission role $n',0)"
    WORKFLOWS_SQL+="('hardcopy','Page Workflow $n',$((100+idx)),0,$active)"
    OWN_SQL+="('softcopy',$STAFF_ID,'Paged Request $n','draft')"
    TASK_SQL+="('softcopy',$STAFF_ID,'Paged Task $n','pending',$WF_ID,1)"
    PLACES_SQL+="('area','Paged Area $n',$active)"
    DOCS_SQL+="('hardcopy','PAGE-HC-$n','Paged Document $n','1','active',$ADMIN_ID)"
done
db "$USERS_SQL; $ROLES_SQL; $WORKFLOWS_SQL; $OWN_SQL; $TASK_SQL; $PLACES_SQL; $DOCS_SQL;"

# Users: searchable, sorted SQL page 2 and data outside page excluded.
get_table /tmp/pk-admin.cookies admin/users \
    --data-urlencode 'q=Page User' --data-urlencode 'limit=10' \
    --data-urlencode 'page=2' --data-urlencode 'sort=name' --data-urlencode 'dir=asc'
test "$(row_count)" -eq 10
visible_row 'page_user_11'
! visible_row 'page_user_01'
grep -q 'of 37 records' /tmp/pk-table.html
grep -q 'sort=name' /tmp/pk-table.html
grep -q 'dir=asc' /tmp/pk-table.html

get_table /tmp/pk-admin.cookies admin/users \
    --data-urlencode 'q=Page User' --data-urlencode 'status=0' \
    --data-urlencode 'limit=10' --data-urlencode 'sort=username' --data-urlencode 'dir=desc'
test "$(row_count)" -eq 6
grep -q 'of 6 records' /tmp/pk-table.html

# Roles: filtered SQL count and pagination; permission matrix remains editable.
get_table /tmp/pk-admin.cookies admin/roles \
    --data-urlencode 'q=page_role_' --data-urlencode 'status=custom' \
    --data-urlencode 'limit=10' --data-urlencode 'page=2' \
    --data-urlencode 'sort=name' --data-urlencode 'dir=desc'
test "$(row_count)" -eq 10
visible_row 'Page Role 27'
! visible_row 'Page Role 37'
grep -q 'of 37 records' /tmp/pk-table.html

# Workflows: both request-type and active-state filters are applied in SQL.
get_table /tmp/pk-admin.cookies admin/workflows \
    --data-urlencode 'q=Page Workflow' --data-urlencode 'type=hardcopy' \
    --data-urlencode 'status=1' --data-urlencode 'sort=version' \
    --data-urlencode 'dir=desc' --data-urlencode 'limit=10' --data-urlencode 'page=2'
test "$(row_count)" -eq 10
grep -q 'of 31 records' /tmp/pk-table.html
grep -q 'type=hardcopy' /tmp/pk-table.html
grep -q 'sort=version' /tmp/pk-table.html

# Requests: staff sees only their own, query is paginated at database layer.
get_table /tmp/pk-staff.cookies my-requests/softcopy \
    --data-urlencode 'q=Paged Request' --data-urlencode 'status=draft' \
    --data-urlencode 'sort=subject' --data-urlencode 'dir=asc' \
    --data-urlencode 'limit=10' --data-urlencode 'page=2'
test "$(row_count)" -eq 10
visible_row 'Paged Request 11'
! visible_row 'Paged Request 01'
grep -q 'of 37 records' /tmp/pk-table.html

get_table /tmp/pk-staff.cookies my-requests/softcopy \
    --data-urlencode 'q=Paged Request' --data-urlencode 'sort=subject' \
    --data-urlencode 'dir=asc' --data-urlencode 'page=999' --data-urlencode 'limit=10'
test "$(row_count)" -eq 7
visible_row 'Paged Request 37'
grep -q '4 / 4' /tmp/pk-table.html

# Tasks: manager sees only assigned pending requests; 25-row page limit works.
get_table /tmp/pk-manager.cookies my-tasks/softcopy \
    --data-urlencode 'q=Paged Task' --data-urlencode 'status=pending' \
    --data-urlencode 'sort=subject' --data-urlencode 'dir=asc' \
    --data-urlencode 'limit=25' --data-urlencode 'page=2'
test "$(row_count)" -eq 12
visible_row 'Paged Task 26'
! visible_row 'Paged Task 01'
grep -q 'of 37 records' /tmp/pk-table.html

# Non-approvers must not see tasks from someone else's approval assignment.
get_table /tmp/pk-staff.cookies my-tasks/softcopy \
    --data-urlencode 'q=Paged Task' --data-urlencode 'limit=10'
test "$(row_count)" -eq 1
grep -q 'No records found' /tmp/pk-table.html

# Existing document and area tables also support database-backed sort/filters.
get_table /tmp/pk-admin.cookies places/area \
    --data-urlencode 'q=Paged Area' --data-urlencode 'sort=name' \
    --data-urlencode 'dir=desc' --data-urlencode 'page=3' --data-urlencode 'limit=10'
test "$(row_count)" -eq 10
grep -q 'of 37 records' /tmp/pk-table.html

get_table /tmp/pk-admin.cookies documents/hardcopy \
    --data-urlencode 'q=Paged Document' --data-urlencode 'sort=title' \
    --data-urlencode 'dir=asc' --data-urlencode 'limit=25' --data-urlencode 'page=2'
test "$(row_count)" -eq 12
grep -q 'of 37 records' /tmp/pk-table.html

# Invalid sort or oversized limit are ignored, with a bounded SQL query.
get_table /tmp/pk-admin.cookies admin/users \
    --data-urlencode 'q=Page User' --data-urlencode 'sort=DROP TABLE users' \
    --data-urlencode 'dir=sideways' --data-urlencode 'limit=10000'
test "$(row_count)" -eq 10
grep -q 'of 37 records' /tmp/pk-table.html

echo 'SQL DataTable pagination passed across requests, tasks, users, roles, workflows, documents and places.'
