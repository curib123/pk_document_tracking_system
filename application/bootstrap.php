<?php
// Shared web and CLI paths. Nothing here starts a separate API.
defined('PK_ROOT') || define('PK_ROOT', dirname(__DIR__));

function pk_base_url()
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php'));
    return $scheme . '://' . $host . rtrim($path, '/') . '/';
}

foreach (['storage', 'storage/logs', 'storage/sessions'] as $folder) {
    $path = PK_ROOT . '/' . $folder;
    if (!is_dir($path)) {
        mkdir($path, 0700, true);
    }
}
