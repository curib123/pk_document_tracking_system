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
