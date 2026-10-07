<?php
require_once dirname(__DIR__).'/application/bootstrap.php';
define('ENVIRONMENT','development');
// CI3 emits legacy dynamic-property deprecations on PHP 8.2+. Other errors are logged privately.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT); ini_set('display_errors','0'); ini_set('log_errors','1');
$system=PK_ROOT.'/vendor/codeigniter/framework/system';
if (!is_file($system.'/core/CodeIgniter.php')) {
    http_response_code(503); header('Content-Type: text/plain; charset=utf-8');
    exit('Dependencies are missing. Run composer install, start XAMPP Apache/MySQL, create the pk_dts database, then run C:\\xampp\\php\\php.exe bin\\install.php.');
}
define('SELF','index.php'); define('FCPATH',__DIR__.DIRECTORY_SEPARATOR); define('BASEPATH',$system.DIRECTORY_SEPARATOR); define('SYSDIR','system');
define('APPPATH',PK_ROOT.'/application/'); define('VIEWPATH',APPPATH.'views/');
require_once BASEPATH.'core/CodeIgniter.php';
