<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = true;

/*
 * XAMPP/MySQL local config.
 *
 * Normal app:
 * - host: 127.0.0.1
 * - port: 3306
 * - database: pk_dts
 * - username: root
 * - password: empty
 *
 * Test overrides are accepted only when PK_TEST_DB=1 and the database
 * name ends in _test. Normal runtime does not read DB credentials from .env.
 */
$testMode = getenv('PK_TEST_DB') === '1';

$testName = $testMode
    ? (string) (getenv('DB_DATABASE') ?: '')
    : '';

if (
    $testMode
    && !preg_match(
        '/^[a-zA-Z0-9_]+_test$/',
        $testName
    )
) {
    throw new RuntimeException(
        'Test database name must end in _test.'
    );
}

// Diri ra ang test override; normal XAMPP values stay obvious and editable.
$host = $testMode
    ? (string) (getenv('DB_HOST') ?: '127.0.0.1')
    : '127.0.0.1';

$name = $testMode
    ? $testName
    : 'pk_dts';

$port = $testMode
    ? (int) (getenv('DB_PORT') ?: 3306)
    : 3306;

$username = $testMode
    ? (string) (getenv('DB_USERNAME') ?: 'root')
    : 'root';

$password = $testMode
    ? (string) (getenv('DB_PASSWORD') ?: '')
    : '';

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
