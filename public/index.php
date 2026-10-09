<?php
require_once dirname(__DIR__) . '/application/bootstrap.php';
define('ENVIRONMENT', getenv('CI_ENV') ?: 'production');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', ENVIRONMENT === 'development' ? '1' : '0');
ini_set('log_errors', '1');

$system = PK_ROOT . '/vendor/codeigniter/framework/system';
if (!is_file($system . '/core/CodeIgniter.php')) {
    http_response_code(503);
    exit('Missing CodeIgniter. Run composer install.');
}
define('SELF', 'index.php');
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
define('BASEPATH', $system . DIRECTORY_SEPARATOR);
define('SYSDIR', 'system');
define('APPPATH', PK_ROOT . '/application/');
define('VIEWPATH', APPPATH . 'view/');
require BASEPATH . 'core/CodeIgniter.php';
