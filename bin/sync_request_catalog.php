<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/application/bootstrap.php';
use Pk\Core\Database;

try {
    $db = Database::connect();
    $version = (int)($db->one('SELECT MAX(version) AS version FROM schema_migrations')['version'] ?? 0);
    if ($version < 7) {
        throw new RuntimeException('Run bin/migrate.php before adding request-catalog permissions.');
    }
    $created = $db->transaction(function () use ($db): bool {
        $permission = $db->one("SELECT id FROM permissions WHERE module_key='documents' AND action_key='request_catalog' LIMIT 1");
        if ($permission) {
            return false; // Preserve role customizations on every subsequent run.
        }
        $id = $db->insert('permissions', [
            'name' => 'Documents: Request Catalog',
            'module_key' => 'documents',
            'module_label' => 'Documents',
            'action_key' => 'request_catalog',
            'action_label' => 'Request Catalog',
            'description' => 'Discover active document titles/numbers only in authorized Access or Assignment requests. Does not grant document or file access.',
        ]);
        foreach (['Staff', 'Administrator', 'Document Control Officer', 'Plant Manager'] as $name) {
            $role = $db->one('SELECT id FROM roles WHERE name=? LIMIT 1', [$name]);
            if ($role) {
                $db->query('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)', [(int)$role['id'], $id]);
            }
        }
        return true;
    });
    echo $created
        ? "Request Catalog permission added. Existing document visibility and approval rules are unchanged.\n"
        : "Request Catalog already exists; role customizations were preserved.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
