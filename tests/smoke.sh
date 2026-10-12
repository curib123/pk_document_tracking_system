#!/usr/bin/env bash
set -euo pipefail
DB_NAME="${PK_TEST_DB:-pk_dts_test}"
DB_HOST="${PK_TEST_HOST:-127.0.0.1}"
DB_PASSWORD="${PK_TEST_PASSWORD:-rootpass}"
if command -v mariadb >/dev/null 2>&1; then DB_CLIENT=mariadb;
elif command -v mysql >/dev/null 2>&1; then DB_CLIENT=mysql;
else echo "MariaDB client missing" >&2; exit 1; fi
db() { MYSQL_PWD="$DB_PASSWORD" "$DB_CLIENT" -h "$DB_HOST" -u root "$DB_NAME" "$@"; }
echo "Loading schema and static roles into disposable database..."
db < database/pk_dts.sql
db < database/seed.sql
db -e "INSERT INTO users (id,username,first_name,last_name,position_title,role_id,password_hash,active) VALUES (1,'ci-admin','CI','Administrator','QA',1,'unusable-ci-fixture',1);"
echo "Seeding nine workflow definitions twice (must remain idempotent)..."
db < database/seed_workflows.sql >/dev/null
db < database/seed_workflows.sql >/dev/null
definitions="$(db -N -e 'SELECT COUNT(*) FROM workflows;')"
test "$definitions" = "9" || { echo "Expected 9 workflows, got $definitions" >&2; exit 1; }
db -e "INSERT INTO workflow_versions (workflow_id,version_number,status,is_default,graph,created_by,published_at) SELECT id,1,'published',1,'{\"steps\":[{\"key\":\"step_1\",\"name\":\"Requester\",\"approver\":{\"type\":\"requester\"}}]}',1,NOW() FROM workflows WHERE workflow_key='softcopy_create';"
db < database/seed_workflows.sql >/dev/null
published="$(db -N -e "SELECT COUNT(*) FROM workflow_versions WHERE status='published' AND is_default=1;")"
test "$published" = "1" || { echo "Published version changed after seeding" >&2; exit 1; }
echo "Verifying one-time migration and several Hardcopy documents per Location..."
# Emulate the original production UNIQUE index, then apply the upgrade SQL.
db -e "ALTER TABLE hardcopy_documents DROP INDEX idx_hardcopy_documents_location, ADD UNIQUE KEY location_id (location_id);"
db < database/migrations/20261013_allow_multiple_hardcopies_per_location.sql
db -e "INSERT INTO areas(id,name,active) VALUES (99901,'Test area',1);"
db -e "INSERT INTO specifics(id,area_id,name,active) VALUES (99901,99901,'Test specific',1);"
db -e "INSERT INTO locations(id,area_id,specific_id,asset_id,name,code,active) VALUES (99901,99901,99901,NULL,'Test location','TEST-L-99901',1);"
db -e "INSERT INTO hardcopy_documents(title,area_id,specific_id,asset_id,location_id,holder_id,status,created_by,creation_source,creation_reason) VALUES ('Document A',99901,99901,NULL,99901,1,'active',1,'direct','test'),('Document B',99901,99901,NULL,99901,1,'active',1,'direct','test');"
documents="$(db -N -e 'SELECT COUNT(*) FROM hardcopy_documents WHERE location_id=99901;')"
test "$documents" = "2" || { echo "Expected two documents at same Location"; exit 1; }
index_unique="$(db -N -e "SELECT NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hardcopy_documents' AND INDEX_NAME='idx_hardcopy_documents_location' LIMIT 1;")"
test "$index_unique" = "1" || { echo "Hardcopy Location index must be non-unique"; exit 1; }
echo "Schema, workflows, and multiple Hardcopy documents per Location migration passed."
