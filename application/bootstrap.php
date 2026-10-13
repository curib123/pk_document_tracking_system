<?php
// Shared paths for CodeIgniter 3 and CLI; no REST endpoints.
defined('PK_ROOT') OR define('PK_ROOT', dirname(__DIR__));

function pk_base_url()
{
    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $prefix = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/public/index.php')));
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($prefix, '/') . '/';
}
foreach (['storage','storage/logs','storage/sessions','storage/documents'] as $part) {
    $directory = PK_ROOT . '/' . $part;
    if (!is_dir($directory)) mkdir($directory, 0700, TRUE);
}
// The first request each day also enforces retention when no OS task is running.
// A failed cleanup must not prevent users from accessing the application.
try {
    require_once __DIR__.'/services/backup/backup_service.php';
    $retention=(new Backup_service(PK_ROOT))->apply_retention_daily();
    if ($retention!==NULL && $retention['failed'])
        error_log('PK DTS daily retention is incomplete; retry tools/backup.php retention.');
} catch (Throwable $error) {
    error_log('PK DTS daily retention failed: '.$error->getMessage());
}
