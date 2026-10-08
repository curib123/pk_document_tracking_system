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
    $applied = $db->transaction(function () use ($db): bool {
        $definitions = [
            'view_assigned' => [
                'View Assigned',
                'View and read active softcopies assigned to the user and hardcopies held by the user.',
            ],
            'view_granted' => [
                'View Granted',
                'View and read documents covered by the users active, unexpired and unrevoked access grants.',
            ],
            'view_all' => [
                'View All',
                'Browse all document metadata. Does not grant unrestricted file-content access.',
            ],
        ];
        $ids = [];
        foreach ($definitions as $action => $definition) {
            $row = $db->one(
                'SELECT id FROM permissions WHERE module_key = ? AND action_key = ? LIMIT 1',
                ['documents', $action]
            );
            if ($row) $ids[$action] = (int) $row['id'];
        }

        // Existing complete catalog means defaults were already introduced.
        // Ayaw i-reset ang later role edits when this command is run again.
        if (count($ids) === count($definitions)) {
            return false;
        }

        foreach ($definitions as $action => [$label, $description]) {
            if (isset($ids[$action])) continue;
            $ids[$action] = $db->insert('permissions', [
                'name' => 'Documents: ' . $label,
                'module_key' => 'documents', 'module_label' => 'Documents',
                'action_key' => $action, 'action_label' => $label,
                'description' => $description,
            ]);
        }

        $staff = $db->one("SELECT id FROM roles WHERE name = 'Staff' LIMIT 1");
        if ($staff) {
            $roleId = (int) $staff['id'];
            foreach (['view_assigned', 'view_granted'] as $action) {
                $db->query(
                    'INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(?,?)',
                    [$roleId, $ids[$action]]
                );
            }
            // Apply the requested restricted Staff default only on first upgrade.
            $db->query(
                "DELETE rp FROM role_permissions rp
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.role_id = ? AND p.module_key = 'documents'
                   AND p.action_key IN ('view_all','access_all')",
                [$roleId]
            );
        }

        $admin = $db->one("SELECT id FROM roles WHERE name = 'Administrator' LIMIT 1");
        if ($admin) {
            foreach ($ids as $id) {
                $db->query(
                    'INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(?,?)',
                    [(int) $admin['id'], $id]
                );
            }
        }

        return true;
    });

    echo $applied
        ? "Document visibility permissions installed. Staff is assigned/granted only. Other role settings were preserved.\n"
        : "Document visibility permissions already exist. Existing role choices were preserved.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Document visibility permission update failed: ' . $error->getMessage() . "\n");
    exit(1);
}
