#!/usr/bin/env bash
set -euo pipefail

# Run only against the disposable pk_dts_test MySQL service.
export PK_TEST_DB=1
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_DATABASE=pk_dts_test
export DB_USERNAME=root
export DB_PASSWORD=rootpass
export PK_ENCRYPTION_KEY=integration-tests-only-do-not-use-in-production
export CI_ENV=development

sed 's/pk_dts/pk_dts_test/g' database/schema.sql | mysql -h127.0.0.1 -uroot -prootpass
sed 's/pk_dts/pk_dts_test/g' database/seed.sql | mysql -h127.0.0.1 -uroot -prootpass
HASH=$(php -r 'echo password_hash("TemporaryTestPassword!2026", PASSWORD_DEFAULT);')
mysql -h127.0.0.1 -uroot -prootpass pk_dts_test -e "
INSERT INTO users (role_id,name,username,email,password_hash)
SELECT id,'Test Admin','pk_test_admin','pk_admin@example.test','$HASH' FROM roles WHERE name='super_admin';
INSERT INTO users (role_id,name,username,email,password_hash)
SELECT id,'Test Staff','pk_test_staff','pk_staff@example.test','$HASH' FROM roles WHERE name='staff';
"

php -S 127.0.0.1:8081 -t public public/index.php >/tmp/pk-server.log 2>&1 &
SERVER_PID=$!
trap 'code=$?; kill "$SERVER_PID" 2>/dev/null || true; if [ "$code" -ne 0 ]; then echo "=== HTTP login response ==="; head -c 5000 /tmp/pk-login.html 2>/dev/null || true; echo; fi; echo "=== PHP server log ==="; tail -80 /tmp/pk-server.log; echo "=== CI application logs ==="; find storage/logs -maxdepth 1 -type f -name "log-*.log" -print -exec tail -90 {} \;' EXIT

for i in {1..20}; do
    if curl -fsS -o /tmp/pk-login.html http://127.0.0.1:8081/login; then break; fi
    sleep 1
done
grep -q 'Sign in to your account' /tmp/pk-login.html

token() {
    grep -o 'name="pk_csrf_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//; s/"$//'
}
curl -fsS -c /tmp/pk-admin.cookies -o /tmp/pk-login.html http://127.0.0.1:8081/login
CSRF=$(token /tmp/pk-login.html)
test -n "$CSRF"

curl -sS -L -b /tmp/pk-admin.cookies -c /tmp/pk-admin.cookies -o /tmp/pk-admin.html \
    --data-urlencode "pk_csrf_token=$CSRF" --data-urlencode 'login=pk_test_admin' \
    --data-urlencode 'password=TemporaryTestPassword!2026' http://127.0.0.1:8081/login
grep -q 'Dashboard' /tmp/pk-admin.html
curl -fsS -b /tmp/pk-admin.cookies -o /tmp/pk-users.html http://127.0.0.1:8081/admin/users
grep -q 'User Management' /tmp/pk-users.html

# CI sessions, CSRF and permission checks must remain active.
curl -fsS -c /tmp/pk-staff.cookies -o /tmp/pk-staff-login.html http://127.0.0.1:8081/login
STAFF_CSRF=$(token /tmp/pk-staff-login.html)
curl -sS -L -b /tmp/pk-staff.cookies -c /tmp/pk-staff.cookies -o /tmp/pk-staff.html \
    --data-urlencode "pk_csrf_token=$STAFF_CSRF" --data-urlencode 'login=pk_test_staff' \
    --data-urlencode 'password=TemporaryTestPassword!2026' http://127.0.0.1:8081/login
grep -q 'Dashboard' /tmp/pk-staff.html
STAFF_CODE=$(curl -sS -b /tmp/pk-staff.cookies -o /tmp/pk-denied.html -w '%{http_code}' http://127.0.0.1:8081/admin/users)
test "$STAFF_CODE" = 403

# The authorized admin may create a master-data area using a normal form POST.
curl -fsS -b /tmp/pk-admin.cookies -c /tmp/pk-admin.cookies -o /tmp/pk-places.html http://127.0.0.1:8081/places/area
PLACE_CSRF=$(token /tmp/pk-places.html)
test -n "$PLACE_CSRF"
curl -sS -b /tmp/pk-admin.cookies -c /tmp/pk-admin.cookies -o /dev/null \
    --data-urlencode "pk_csrf_token=$PLACE_CSRF" --data-urlencode "confirmed=yes" \
    --data-urlencode "name=Quality Control" --data-urlencode "active=1" \
    http://127.0.0.1:8081/places/area/save
COUNT=$(mysql -N -s -h127.0.0.1 -uroot -prootpass pk_dts_test \
    -e "SELECT COUNT(*) FROM places WHERE type='area' AND name='Quality Control'")
test "$COUNT" = 1

# All sidebar pages must render successfully with database data.
for path in dashboard documents/hardcopy documents/softcopy \
    my-requests/softcopy my-requests/hardcopy my-requests/hardcopy-transfer \
    my-requests/access-grant my-requests/document-assign \
    my-tasks/softcopy my-tasks/hardcopy my-tasks/hardcopy-transfer \
    my-tasks/access-grant my-tasks/document-assign \
    places/area places/specific places/asset places/location \
    places/sequence places/softcopy-categories \
    admin/users admin/roles admin/workflows
do
    curl -fsS -b /tmp/pk-admin.cookies \
        -o /tmp/pk-module.html "http://127.0.0.1:8081/$path"
    grep -q '</html>' /tmp/pk-module.html
done

# A five-part request must progress only through its configured approver.
mysql -h127.0.0.1 -uroot -prootpass pk_dts_test -e "
INSERT INTO users (role_id,name,username,email,password_hash)
SELECT id,'Test Manager','pk_test_manager','pk_manager@example.test','$HASH'
FROM roles WHERE name='plant_manager';
INSERT INTO workflows (request_type,name,version,is_default,active)
VALUES ('softcopy','CI Softcopy Review',1,1,1);
INSERT INTO workflow_steps (workflow_id,step_order,label,approver_type,approver_user_id)
SELECT w.id,1,'Manager Review','user',u.id
FROM workflows w CROSS JOIN users u
WHERE w.request_type='softcopy' AND u.username='pk_test_manager';
"

curl -fsS -b /tmp/pk-staff.cookies -o /tmp/pk-requests.html \
    http://127.0.0.1:8081/my-requests/softcopy
REQ_CSRF=$(token /tmp/pk-requests.html)
curl -sS -b /tmp/pk-staff.cookies -c /tmp/pk-staff.cookies -o /dev/null \
    --data-urlencode "pk_csrf_token=$REQ_CSRF" --data-urlencode 'confirmed=yes' \
    --data-urlencode 'subject=Quality document approval' \
    http://127.0.0.1:8081/my-requests/softcopy/save
REQ_ID=$(mysql -N -s -h127.0.0.1 -uroot -prootpass pk_dts_test \
    -e "SELECT id FROM requests WHERE subject='Quality document approval' AND status='draft' LIMIT 1")
test -n "$REQ_ID"
curl -fsS -b /tmp/pk-staff.cookies -o /tmp/pk-requests.html \
    http://127.0.0.1:8081/my-requests/softcopy
REQ_CSRF=$(token /tmp/pk-requests.html)
curl -sS -b /tmp/pk-staff.cookies -c /tmp/pk-staff.cookies -o /dev/null \
    --data-urlencode "pk_csrf_token=$REQ_CSRF" --data-urlencode 'confirmed=yes' \
    --data-urlencode "id=$REQ_ID" \
    http://127.0.0.1:8081/my-requests/softcopy/submit
CURRENT=$(mysql -N -s -h127.0.0.1 -uroot -prootpass pk_dts_test \
    -e "SELECT status FROM requests WHERE id=$REQ_ID")
test "$CURRENT" = pending

curl -fsS -c /tmp/pk-manager.cookies -o /tmp/pk-manager-login.html \
    http://127.0.0.1:8081/login
MANAGER_CSRF=$(token /tmp/pk-manager-login.html)
curl -sS -L -b /tmp/pk-manager.cookies -c /tmp/pk-manager.cookies -o /tmp/pk-manager.html \
    --data-urlencode "pk_csrf_token=$MANAGER_CSRF" \
    --data-urlencode 'login=pk_test_manager' \
    --data-urlencode 'password=TemporaryTestPassword!2026' \
    http://127.0.0.1:8081/login
grep -q 'Dashboard' /tmp/pk-manager.html
curl -fsS -b /tmp/pk-manager.cookies -o /tmp/pk-tasks.html \
    http://127.0.0.1:8081/my-tasks/softcopy
grep -q 'Quality document approval' /tmp/pk-tasks.html
MANAGER_CSRF=$(token /tmp/pk-tasks.html)
curl -sS -b /tmp/pk-manager.cookies -c /tmp/pk-manager.cookies -o /dev/null \
    --data-urlencode "pk_csrf_token=$MANAGER_CSRF" --data-urlencode 'confirmed=yes' \
    --data-urlencode "id=$REQ_ID" --data-urlencode 'decision=approved' \
    http://127.0.0.1:8081/my-tasks/softcopy/decide
CURRENT=$(mysql -N -s -h127.0.0.1 -uroot -prootpass pk_dts_test \
    -e "SELECT status FROM requests WHERE id=$REQ_ID")
test "$CURRENT" = approved
DECISION_COUNT=$(mysql -N -s -h127.0.0.1 -uroot -prootpass pk_dts_test \
    -e "SELECT COUNT(*) FROM request_decisions WHERE request_id=$REQ_ID AND decision='approved'")
test "$DECISION_COUNT" = 1
echo "Integration smoke passed: auth, RBAC, documents, places, admin pages, request draft, workflow approval."

