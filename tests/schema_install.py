"""Verify the public install snapshot only in an owned, disposable database."""
import os
from pathlib import Path
import secrets
import subprocess

root = Path(__file__).resolve().parents[1]
database = 'pk_dts_schema_' + secrets.token_hex(6) + '_test'
args = [os.environ.get('PK_MYSQL', r'C:\xampp\mysql\bin\mysql.exe'),
        '-h', os.environ.get('DB_HOST', '127.0.0.1'), '-u', os.environ.get('DB_USERNAME', 'root'), '-N']
env = dict(os.environ, MYSQL_PWD=os.environ.get('DB_PASSWORD', ''))

def sql(statement, selected=True):
    result = subprocess.run(args + ([database] if selected else []), input=statement.encode(),
                            env=env, capture_output=True, check=True)
    return result.stdout.decode().strip()

try:
    sql('CREATE DATABASE `' + database + '` CHARACTER SET utf8mb4;', False)
    sql((root / 'database/pk_dts.sql').read_text(encoding='utf-8'))
    assert sql('SELECT COUNT(*) FROM roles') == '5'
    assert sql("SELECT COUNT(*) FROM permissions WHERE module_key='files' AND action_key IN ('history','print','download')") == '3'
    for table in ['users', 'requests', 'workflow_history', 'files', 'softcopy_documents', 'hardcopy_documents', 'task_notification_receipts', 'user_roles']:
        assert sql('SELECT COUNT(*) FROM ' + table) == '0', table
    assert sql("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='hardcopy_documents' AND column_name='location_id' AND non_unique=0") == '0'
    migration = (root / 'database/migrations/20261015_file_action_permissions.sql').read_text()
    sql("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=5 AND p.module_key='files' AND p.action_key='download'")
    sql(migration)
    assert sql("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE role_id=5 AND p.module_key='files' AND p.action_key='download'") == '0'
    print('Fresh schema imports, contains defaults only, includes current tables/indexes and preserves revoked permissions on migration reruns.')
finally:
    if database.startswith('pk_dts_schema_') and database.endswith('_test'):
        sql('DROP DATABASE IF EXISTS `' + database + '`', False)
