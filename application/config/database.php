<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = true;

/*
 * XAMPP/MySQL local configuration.
 * Default XAMPP MySQL uses 127.0.0.1:3306, user root and an empty password.
 * If your local XAMPP credentials differ, edit the four normal-mode values below.
 *
 * Automated integration tests may override the connection only when PK_TEST_DB=1
 * and the requested database name ends in _test. Normal application requests do
 * not read database credentials from .env or operating-system environment values.
 */
$testMode = getenv('PK_TEST_DB') === '1';
$testName = $testMode ? (string)(getenv('DB_DATABASE') ?: '') : '';
if ($testMode && !preg_match('/^[a-zA-Z0-9_]+_test$/', $testName)) {
    throw new RuntimeException('Test database name must end in _test.');
}

$host = $testMode ? (string)(getenv('DB_HOST') ?: '127.0.0.1') : '127.0.0.1';
$name = $testMode ? $testName : 'pk_dts';
$port = $testMode ? (int)(getenv('DB_PORT') ?: 3306) : 3306;
$username = $testMode ? (string)(getenv('DB_USERNAME') ?: 'root') : 'root';
$password = $testMode ? (string)(getenv('DB_PASSWORD') ?: '') : '';

$db['default'] = [
    'dsn' => '',
    'hostname' => $host,
    'username' => $username,
    'password' => $password,
    'database' => $name,
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => false,
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
