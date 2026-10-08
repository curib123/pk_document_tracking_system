<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/application/bootstrap.php';

use Pk\Core\{Context, Database};

try {
    $db = Database::connect();
    $ctx = new Context($db);

    $maintenance = $ctx->model(
        Maintenance_model::class
    );

    $expiredCount = $db->transaction(
        function () use (
            $ctx,
            $maintenance
        ): int {
            $grants =
                $maintenance->expired_grants();

            foreach ($grants as $grant) {
                $ctx->db->update(
                    'access_grants',
                    (int) $grant['id'],
                    ['status' => 'expired']
                );

                $ctx->audit(
                    'access',
                    'expired',
                    (int) $grant['id'],
                    $grant,
                    ['status' => 'expired'],
                    'Scheduled expiry',
                    (int) $grant['request_id']
                );

                $ctx->notify(
                    (int) $grant['user_id'],
                    'Document access expired',
                    'Your approved access period ended.',
                    (int) $grant['request_id']
                );
            }

            $maintenance->prune_login_attempts();

            return count($grants);
        }
    );

    // Old temp conversion folders lang atong limpyo; fresh workspaces stay untouched.
    $root =
        PK_ROOT
        . '/storage/conversions';

    foreach (
        glob($root . '/*', GLOB_ONLYDIR) ?: []
        as $directory
    ) {
        $recent =
            filemtime($directory)
            > time() - 86400;

        if (is_link($directory) || $recent) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isLink() || $file->isFile()) {
                unlink($file->getPathname());
            } elseif ($file->isDir()) {
                rmdir($file->getPathname());
            }
        }

        rmdir($directory);
    }

    echo
        $expiredCount
        . " access grants expired. Old conversion workspaces cleaned.\n";
} catch (Throwable $error) {
    fwrite(
        STDERR,
        'Maintenance failed: '
        . $error->getMessage()
        . "\n"
    );

    exit(1);
}
