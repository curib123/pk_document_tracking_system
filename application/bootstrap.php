<?php
// Shared paths for CodeIgniter 3 and CLI; no REST endpoints.
defined('PK_ROOT') OR define('PK_ROOT', dirname(__DIR__));
date_default_timezone_set('Asia/Manila');

function pk_base_url()
{
    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/public/index.php'));
    $prefix = dirname($scriptName);
    $prefix = preg_replace('~/public$~', '', $prefix);
    if ($prefix === '.' || $prefix === '/' || $prefix === '\\') {
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $applicationRoot = realpath(PK_ROOT);
        if ($documentRoot !== FALSE && $applicationRoot !== FALSE) {
            $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
            $applicationRoot = str_replace('\\', '/', $applicationRoot);
            $rootLength = strlen($documentRoot);
            $rootMatches = DIRECTORY_SEPARATOR === '\\'
                ? strncasecmp($applicationRoot, $documentRoot, $rootLength) === 0
                : strncmp($applicationRoot, $documentRoot, $rootLength) === 0;
            if ($rootMatches && ($applicationRoot[$rootLength] ?? '') === '/') {
                $prefix = substr($applicationRoot, $rootLength);
            }
        }
    }
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($prefix, '/') . '/';
}
foreach (['storage', 'storage/logs', 'storage/sessions', 'storage/documents'] as $part) {
    $directory = PK_ROOT . '/' . $part;
    if (!is_dir($directory))
        mkdir($directory, 0700, TRUE);
}
