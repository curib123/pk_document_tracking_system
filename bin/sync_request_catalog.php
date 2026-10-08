<?php
declare(strict_types=1);
require dirname(__DIR__) . '/application/bootstrap.php';

use Pk\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $db = Database::connect();
    $db->transaction(function () use ($db): void {
        $existing = $db->one(
            "SELECT id FROM permissions WHERE module_key='documents' AND action_key='request_catalog' FOR UPDATE"
        );
        if ($existing) {
            echo "Request catalog permission already exists. Role customizations preserved.\n";
            return;
        }
        $permissionId = $db->insert('permissions', [
            'name' => 'Documents: Request catalog',
            'module_key' => 'documents',
            'module_label' => 'Documents',
            'action_key' => 'request_catalog',
            'action_label' => 'Request catalog',
            'description' => 'Discover active document references and titles in Access/Assignment request pickers only. Does not grant document or file access.',
        ]);
        foreach (['Administrator', 'Document Control Officer', 'Plant Manager', 'Staff'] as $name) {
            $role = $db->one('SELECT id FROM roles WHERE name=?', [$name]);
            if ($role) {
                $db->query('INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(?,?)', [(int) $role['id'], $permissionId]);
            }
        }
        echo "Request catalog permission installed. Document/file visibility is unchanged.\n";
    });
} catch (Throwable $error) {
    fwrite(STDERR, 'Request catalog upgrade failed: ' . $error->getMessage() . "\n");
    exit(1);
}
