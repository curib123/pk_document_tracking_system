<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/application/bootstrap.php';

use Pk\Core\{Database, Rules, Seed};

try {
    // Ari ta mag-check sa requirements first para fail-fast before touching the DB.
    foreach (
        ['mysqli', 'fileinfo', 'mbstring', 'zip']
        as $extension
    ) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException(
                "PHP extension $extension is required."
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

    $db = Database::connect();
    $databaseName =
        (string) $db->builder->database;

    $exists = $db->first(
        $db->builder
            ->reset_query()
            ->select('COUNT(*) AS n', false)
            ->where(
                'table_schema',
                $databaseName
            )
            ->get('information_schema.tables')
    );

    if ((int) $exists['n'] > 0) {
        throw new RuntimeException(
            'Database is not empty. Installer will not alter existing data. Use a new empty database or an explicit reviewed migration.'
        );
    }

    $schema = file_get_contents(
        PK_ROOT . '/database/schema.sql'
    );

    foreach (explode(';', $schema) as $statement) {
        $statement = trim($statement);

        if ($statement !== '') {
            $db->query($statement);
        }
    }

    Seed::run(
        $db,
        $username,
        $password
    );

    foreach (
        ['files', 'conversions', 'logs']
        as $folder
    ) {
        $path =
            PK_ROOT
            . '/storage/'
            . $folder;

        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }
    }

    echo "Installed schema version 4.\n";
    echo "Username: $username\n";
    echo "Initial password (shown once): $password\n";
    echo "Change this password at first login. Create another approver before using request workflows.\n";
} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Installation failed: '
        . $error->getMessage()
        . "\n"
    );

    exit(1);
}
