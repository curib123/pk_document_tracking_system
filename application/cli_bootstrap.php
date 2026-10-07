<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI bootstrap is not an HTTP entry point.');
$system = PK_ROOT.'/vendor/codeigniter/framework/system/';
if (!is_file($system.'database/DB.php')) throw new RuntimeException('Run composer install to install CodeIgniter.');
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', 'testing');
if (!defined('BASEPATH')) define('BASEPATH', $system);
if (!defined('APPPATH')) define('APPPATH', PK_ROOT.'/application/');
if (!defined('VIEWPATH')) define('VIEWPATH', APPPATH.'views/');
if (!defined('FCPATH')) define('FCPATH', PK_ROOT.'/public/');
require_once APPPATH.'config/constants.php';
require_once BASEPATH.'core/Common.php';
require_once BASEPATH.'database/DB.php';
