<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Simple routes ra; module controllers handle the native endpoints.
$route['default_controller'] = 'app';
$route['404_override'] = '';
$route['translate_uri_dashes'] = false;

$route['login'] = 'auth/index';
