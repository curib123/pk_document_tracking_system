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
trap 'code=$?; kill "$SERVER_PID" 2>/dev/null || true; if [ "$code" -ne 0 ]; then echo "=== HTTP login response ==="; head -c 5000 /tmp/pk-login.html 2>/dev/null || true; echo; fi; echo "=== PHP server log ==="; tail -80 /tmp/pk-server.log' EXIT

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
echo "Integration smoke passed: login, dashboard, admin access, staff denial and area creation."
