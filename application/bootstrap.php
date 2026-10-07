<?php
declare(strict_types=1);

define(
    'PK_ROOT',
    dirname(__DIR__)
);

date_default_timezone_set(
    'Asia/Manila'
);

// Ari ta mag-build sa base URL dynamically para bisan unsa nga htdocs folder name okay ra.
if (!function_exists('pk_base_url')) {
    function pk_base_url(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'http://localhost/';
        }

        $secure =
            !empty($_SERVER['HTTPS'])
            && strtolower(
                (string) $_SERVER['HTTPS']
            ) !== 'off';

        $scheme = $secure
            ? 'https'
            : 'http';

        $host =
            $_SERVER['HTTP_HOST']
            ?? 'localhost';

        $script = str_replace(
            '\\',
            '/',
            (string) (
                $_SERVER['SCRIPT_NAME']
                ?? '/index.php'
            )
        );

        $base = rtrim(
            str_replace(
                '\\',
                '/',
                dirname($script)
            ),
            '/.'
        );

        return
            $scheme
            . '://'
            . $host
            . ($base !== '' ? $base : '')
            . '/';
    }
}

// Composer handles third-party packages; app classes stay CI3-native.
$composerAutoload =
    PK_ROOT
    . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(
    static function (string $name): void {
        static $map = null;

        $map ??= require
            PK_ROOT
            . '/application/config/classmap.php';

        if ($name === 'CI_Model') {
            $path =
                PK_ROOT
                . '/vendor/codeigniter/framework/system/core/Model.php';

            if (is_file($path)) {
                if (!defined('BASEPATH')) {
                    define(
                        'BASEPATH',
                        dirname($path, 2) . '/'
                    );
                }

                require_once $path;
            }

            return;
        }

        if (!isset($map[$name])) {
            return;
        }

        $path =
            PK_ROOT
            . '/application/'
            . $map[$name];

        if (is_file($path)) {
            require_once $path;
        }
    },
    true,
    true
);
