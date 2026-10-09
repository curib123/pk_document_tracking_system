#!/usr/bin/env bash
set -euo pipefail
export PK_TEST_DB=1 DB_DATABASE=pk_dts_test DB_HOST=127.0.0.1 DB_USERNAME=root DB_PASSWORD=rootpass
export PK_ENCRYPTION_KEY=test-only CI_ENV=development

mysql -h127.0.0.1 -uroot -prootpass pk_dts_test < database/pk_dts.sql
mysql -h127.0.0.1 -uroot -prootpass pk_dts_test < database/seed.sql
HASH=$(php -r 'echo password_hash("TemporaryTestPassword2026!", PASSWORD_DEFAULT);')
mysql -h127.0.0.1 -uroot -prootpass pk_dts_test -e "
INSERT INTO users(username,first_name,last_name,position_title,role_id,password_hash,require_password_change,active)
SELECT 'test_admin','Test','Administrator','System Admin',id,'$HASH',0,1
FROM roles WHERE name='Administrator';
"
# Import original workflow graph presets only AFTER the administrator exists.
mysql -h127.0.0.1 -uroot -prootpass pk_dts_test < database/seed_workflows.sql
php tests/source_schema_contract.php

php -S 127.0.0.1:8089 -t public public/index.php >/tmp/pk-server.log 2>&1 &
PID=$!
trap 'code=$?;kill "$PID" 2>/dev/null||true; if [ "$code" -ne 0 ]; then echo "=== PHP server ==="; tail -75 /tmp/pk-server.log; echo "=== CI log ==="; for f in storage/logs/*.log; do [ -f "$f" ] && tail -75 "$f"; done; fi' EXIT

for i in {1..20}; do
  if curl -fsS -o /tmp/pk-login.html http://127.0.0.1:8089/login; then break; fi
  sleep 1
done
grep -q 'Sign in to your account' /tmp/pk-login.html

curl -fsS -c /tmp/pk-cookie -o /tmp/pk-login.html http://127.0.0.1:8089/login
TOKEN=$(grep -o 'name="pk_csrf_token" value="[^"]*"' /tmp/pk-login.html | head -1 | sed 's/.*value="//;s/"$//')
test -n "$TOKEN"
curl -sS -L -b /tmp/pk-cookie -c /tmp/pk-cookie -o /tmp/pk-home.html \
  --data-urlencode "pk_csrf_token=$TOKEN" --data-urlencode 'login=test_admin' \
  --data-urlencode 'password=TemporaryTestPassword2026!' http://127.0.0.1:8089/login
grep -q 'Dashboard' /tmp/pk-home.html
for route in dashboard documents/hardcopy documents/softcopy places/area places/specific \
 places/asset places/location places/sequence places/softcopy-categories \
 my-requests/softcopy my-requests/hardcopy my-requests/hardcopy-transfer \
 my-requests/access-grant my-requests/document-assign \
 my-tasks/softcopy my-tasks/hardcopy my-tasks/hardcopy-transfer \
 my-tasks/access-grant my-tasks/document-assign \
 admin/users admin/roles admin/workflows; do
  curl -fsS -b /tmp/pk-cookie -o /tmp/pk-module.html "http://127.0.0.1:8089/$route"
  grep -q '</html>' /tmp/pk-module.html
done
echo 'Authoritative pk_dts SQL import, login and 22 module routes passed.'
source tests/workflow_smoke.sh
source tests/places_smoke.sh
