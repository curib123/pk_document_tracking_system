<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/application/bootstrap.php';

use Pk\Core\{Database, Rules, Seed};

try {
    foreach (
        ['mysqli', 'fileinfo', 'mbstring', 'zip']
        as $extension
    ) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException(
                'PHP extension '
                . $extension
                . ' is required.'
            );
        }
    }

    $ciPath =
        PK_ROOT
        . '/vendor/codeigniter/framework/system/core/CodeIgniter.php';

    if (!is_file($ciPath)) {
        throw new RuntimeException(
            'Run composer install first.'
        );
    }

    $db = Database::connect();

    $requiredTables = [
        'permissions',
        'roles',
        'role_permissions',
        'users',
        'workflows',
        'workflow_versions',
        'settings',
    ];

    // Existing schema only; seed command never creates tables behind the scenes.
    foreach ($requiredTables as $table) {
        $exists = $db->first(
            $db->builder
                ->reset_query()
                ->select('COUNT(*) AS n', false)
                ->where(
                    'table_schema',
                    (string) $db->builder->database
                )
                ->where('table_name', $table)
                ->get('information_schema.tables')
        );

        if ((int) $exists['n'] !== 1) {
            throw new RuntimeException(
                'Database schema is missing. Run bin/install.php or import database/schema.sql first.'
            );
        }
    }

    $username =
        Seed::defaultAdmin()['username'];

    $testPassword =
        getenv('PK_TEST_DB') === '1'
            ? (string) (
                getenv('PK_ADMIN_PASSWORD') ?: ''
            )
            : '';

    $password = $testPassword !== ''
        ? $testPassword
        : bin2hex(random_bytes(12));

    Rules::password(
        $password,
        $password
    );

    Seed::run(
        $db,
        $username,
        $password
    );

    echo "Default seed completed.\n";
    echo "Username: $username\n";
    echo "Initial password (shown once): $password\n";
    echo "The admin account must change this password at first login.\n";
} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Seeding failed: '
        . $error->getMessage()
        . "\n"
    );

    exit(1);
}
