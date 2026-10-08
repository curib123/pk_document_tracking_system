<?php
declare(strict_types=1);
require dirname(__DIR__) . '/application/bootstrap.php';

use Pk\Core\Database;

if (getenv('PK_TEST_DB') !== '1' || !str_ends_with(getenv('DB_DATABASE') ?: '', '_test')) {
    fwrite(STDERR, "Use a dedicated *_test database with PK_TEST_DB=1.\n");
    exit(2);
}

$db = Database::connect();
$checks = 0;
function syncCheck(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($name);
    ++$checks;
    echo "PASS $name\n";
}
function syncRun(): void
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PK_ROOT . '/bin/sync_document_visibility.php');
    exec($command . ' 2>&1', $output, $status);
    syncCheck($status === 0, 'permission update command succeeds: ' . implode(' ', $output));
}
function roleKeys(Database $db, int $role): array
{
    return array_column($db->all(
        "SELECT CONCAT(p.module_key,'.',p.action_key) AS capability
         FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
         WHERE rp.role_id=? ORDER BY capability", [$role]
    ), 'capability');
}

$staff = (int) $db->one("SELECT id FROM roles WHERE name='Staff'")['id'];
$dco = (int) $db->one("SELECT id FROM roles WHERE name='Document Control Officer'")['id'];
$admin = (int) $db->one("SELECT id FROM roles WHERE name='Administrator'")['id'];
$usersBefore = (int) $db->one('SELECT COUNT(*) AS n FROM users')['n'];
$schemaBefore = (int) $db->one('SELECT MAX(version) AS n FROM schema_migrations')['n'];
$allAccess = (int) $db->one("SELECT id FROM permissions WHERE module_key='documents' AND action_key='access_all'")['id'];
$custom = $db->insert('roles', ['name' => 'Upgrade preservation ' . bin2hex(random_bytes(4))]);
$db->query('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)', [$custom, $allAccess]);

// Simulate a real v7 installation with no new visibility permissions.
$db->query("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
    WHERE p.module_key='documents' AND p.action_key IN ('view_assigned','view_granted','view_all')");
$db->query("DELETE FROM permissions WHERE module_key='documents' AND action_key IN ('view_assigned','view_granted','view_all')");
$db->query('INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(?,?)', [$staff, $allAccess]);
$dcoBefore = roleKeys($db, $dco);
$customBefore = roleKeys($db, $custom);

syncRun();
$staffKeys = roleKeys($db, $staff);
syncCheck(in_array('documents.view_assigned', $staffKeys, true) && in_array('documents.view_granted', $staffKeys, true), 'existing Staff receives assigned and granted scopes');
syncCheck(!in_array('documents.view_all', $staffKeys, true) && !in_array('documents.access_all', $staffKeys, true), 'first upgrade removes Staff all-document overrides');
syncCheck(roleKeys($db, $dco) === $dcoBefore, 'DCO permissions are not reset');
syncCheck(roleKeys($db, $custom) === $customBefore, 'custom role privileges are preserved');
syncCheck(in_array('documents.view_all', roleKeys($db, $admin), true), 'Administrator can manage all new capabilities');
syncCheck((int) $db->one('SELECT COUNT(*) AS n FROM users')['n'] === $usersBefore, 'existing user records are preserved');
syncCheck((int) $db->one('SELECT MAX(version) AS n FROM schema_migrations')['n'] === $schemaBefore, 'permission upgrade does not change structural schema version');

// Re-running deployment must not undo later administrator choices.
$granted = (int) $db->one("SELECT id FROM permissions WHERE module_key='documents' AND action_key='view_granted'")['id'];
$db->query('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?', [$staff, $granted]);
$customized = roleKeys($db, $staff);
syncRun();
syncCheck(roleKeys($db, $staff) === $customized, 'repeated command preserves later role customization');
syncCheck((int) $db->one("SELECT COUNT(*) AS n FROM permissions WHERE module_key='documents' AND action_key IN ('view_assigned','view_granted','view_all')")['n'] === 3, 'permission update never duplicates capability rows');

$db->query('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)', [$staff, $granted]);
$db->query('DELETE FROM role_permissions WHERE role_id=?', [$custom]);
$db->query('DELETE FROM roles WHERE id=?', [$custom]);
echo "$checks permission upgrade assertions passed.\n";
