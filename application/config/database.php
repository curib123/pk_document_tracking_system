<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$active_group = 'default';
$query_builder = TRUE;
// Never hard-code production credentials. Test DB must be separate.
$test = getenv('PK_TEST_DB') === '1';
$name = $test ? (getenv('DB_DATABASE') ?: '') : (getenv('DB_DATABASE') ?: 'pk_dts');
if ($test && !preg_match('/^[a-zA-Z0-9_]+_test$/', $name)) {
    throw new RuntimeException('Test database name must end in _test.');
}
$db['default'] = [
 'dsn' => '', 'hostname' => getenv('DB_HOST') ?: '127.0.0.1',
 'username' => getenv('DB_USERNAME') ?: 'root',
 'password' => getenv('DB_PASSWORD') ?: '',
 'database' => $name, 'dbdriver' => 'mysqli', 'dbprefix' => '',
 'pconnect' => FALSE, 'db_debug' => FALSE, 'cache_on' => FALSE,
 'cachedir' => '', 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_general_ci',
 'swap_pre' => '', 'encrypt' => FALSE, 'compress' => FALSE,
 'stricton' => TRUE, 'failover' => [], 'save_queries' => FALSE,
 'port' => (int) (getenv('DB_PORT') ?: 3306)
];
