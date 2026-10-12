#!/usr/bin/env bash
set -euo pipefail
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=pk_dts_test
export DB_USERNAME=root DB_PASSWORD=rootpass
export PK_BACKUP_PASSWORD='test-local-encrypted-zip-password'
export PK_BACKUP_SIGNING_KEY='ci-only-signing-key-never-use-outside-ci-123456789'
export PK_BACKUP_DIR="$PWD/storage/backups"
export PK_MYSQLDUMP="$(command -v mysqldump || command -v mariadb-dump)"
export PK_MYSQL="$(command -v mysql || command -v mariadb)"
mkdir -p storage/documents storage/audit storage/custom
echo 'controlled fixture' > storage/documents/ci-restorable-document
echo '[{"event":"ci"}]' > storage/audit/ci-archive.json
echo 'custom private data' > storage/custom/ci-private.txt
php tools/backup.php manual
archive="$(find storage/backups -maxdepth 1 -name 'pk-dts-manual-*.zip' -type f | head -n 1)"
test -n "$archive" || { echo "Backup ZIP was not created"; exit 1; }
name="$(basename "$archive")"
php tools/backup.php verify "$name"

db() { MYSQL_PWD="$DB_PASSWORD" "$PK_MYSQL" -h "$DB_HOST" -u "$DB_USERNAME" "$DB_DATABASE" "$@"; }
db -e "UPDATE users SET first_name='Changed' WHERE username='ci-admin';"
echo 'after-backup' > storage/documents/ci-restorable-document
echo 'after-backup' > storage/audit/ci-archive.json
echo 'after-backup' > storage/custom/ci-private.txt
php tools/backup.php restore "$name" --confirm

first_name="$(db -N -e "SELECT first_name FROM users WHERE username='ci-admin' LIMIT 1;")"
test "$first_name" = "CI" || { echo "Database rows were not restored"; exit 1; }
test "$(cat storage/documents/ci-restorable-document)" = "controlled fixture" || {
    echo "Controlled document contents were not restored"; exit 1;
}
test "$(cat storage/audit/ci-archive.json)" = '[{"event":"ci"}]' || {
    echo "Audit files were not restored"; exit 1;
}
test "$(cat storage/custom/ci-private.txt)" = "custom private data" || {
    echo "Other private system files were not restored"; exit 1;
}
recovery="$(find storage/backups -maxdepth 1 -name 'pk-dts-recovery-*.zip' -type f | head -n 1)"
test -n "$recovery" || { echo "Restore recovery ZIP was not created"; exit 1; }
php tools/backup.php verify "$(basename "$recovery")"
echo "Full encrypted backup, verify, restore, recovery ZIP, DB and private files integration passed."
