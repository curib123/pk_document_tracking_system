<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Native CodeIgniter sessions, database and URL helpers. No REST layer.
$autoload = [
    'packages' => [],
    'libraries' => ['database', 'session'],
    'drivers' => [],
    'helper' => ['url', 'form'],
    'config' => [],
    'language' => [],
    'model' => [],
];
