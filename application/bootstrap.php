<?php
declare(strict_types=1);
define('PK_ROOT', dirname(__DIR__));
if (is_file(PK_ROOT . '/.env')) {
    foreach (file(PK_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}
date_default_timezone_set('Asia/Manila');
if (is_file(PK_ROOT . '/vendor/autoload.php')) require_once PK_ROOT . '/vendor/autoload.php';
else spl_autoload_register(function (string $name): void {
    if (str_starts_with($name, 'Pk\\')) {
        $path = PK_ROOT . '/application/src/' . str_replace('\\', '/', substr($name, 3)) . '.php';
        if (is_file($path)) require_once $path;
    }
});
