<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = true;
$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_DATABASE') ?: 'pk_dts';
$port = filter_var(getenv('DB_PORT') ?: '3306', FILTER_VALIDATE_INT);
if (!preg_match('/^[a-zA-Z0-9_]+$/', $name) || !$port || $port < 1 || $port > 65535) {
    throw new RuntimeException('Invalid database name or port.');
}
$db['default'] = [
    'dsn' => '',
    'hostname' => $host,
    'username' => getenv('DB_USERNAME') ?: '',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => $name,
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => false,
    // Services return a safe JSON error instead of CI3 printing database details.
    'db_debug' => false,
    'cache_on' => false,
    'cachedir' => '',
    'char_set' => 'utf8mb4',
    'dbcollat' => 'utf8mb4_unicode_ci',
    'swap_pre' => '',
    'encrypt' => false,
    'compress' => false,
    'stricton' => true,
    'failover' => [],
    'save_queries' => false,
    'port' => $port,
];
