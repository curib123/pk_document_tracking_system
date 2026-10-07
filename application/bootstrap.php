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
// Composer supplies third-party dependencies; application paths remain CI3-native.
if (is_file(PK_ROOT.'/vendor/autoload.php')) require_once PK_ROOT.'/vendor/autoload.php';
spl_autoload_register(static function (string $name): void {
    static $map=null,$aliases=null;
    $map ??= require PK_ROOT.'/application/config/classmap.php';
    $aliases ??= require PK_ROOT.'/application/config/class_aliases.php';
    if (isset($aliases[$name])) {
        if (class_exists($aliases[$name]) && !class_exists($name,false)) class_alias($aliases[$name],$name);
        return;
    }
    if ($name==='CI_Model') {
        $path=PK_ROOT.'/vendor/codeigniter/framework/system/core/Model.php';
        if (is_file($path)) {
            if (!defined('BASEPATH')) define('BASEPATH',dirname($path,2).'/');
            require_once $path;
        }
        return;
    }
    if (isset($map[$name])) {
        $path=PK_ROOT.'/application/'.$map[$name];
        if (is_file($path)) require_once $path;
    }
},true,true);
