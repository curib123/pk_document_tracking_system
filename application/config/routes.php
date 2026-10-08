<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Preserve the existing workspace routes until feature parity is verified.
$route['default_controller'] = 'web_dashboard';
$route['404_override'] = '';
$route['translate_uri_dashes'] = false;
$route['login'] = 'web_auth/index';

// Native Bootstrap browser routes: server-rendered HTML and POST/redirect/GET.
$route['web'] = 'web_dashboard/index';
$route['web/login'] = 'web_auth/index';
$route['web/sign-in'] = 'web_auth/sign_in';
$route['web/logout'] = 'web_auth/logout';
$route['web/password'] = 'web_auth/password';
$route['web/change-password'] = 'web_auth/change_password';

$route['web/catalog/(:any)/new'] = 'web_catalog/create/$1';
$route['web/catalog/(:any)/edit/(:num)'] = 'web_catalog/edit/$1/$2';
$route['web/catalog/(:any)/delete/(:num)'] = 'web_catalog/confirm_delete/$1/$2';
$route['web/catalog/(:any)/lookup'] = 'web_catalog/lookup/$1';
$route['web/catalog/(:any)/save'] = 'web_catalog/save/$1';
$route['web/catalog/(:any)/remove'] = 'web_catalog/delete/$1';
$route['web/catalog/(:any)'] = 'web_catalog/index/$1';

$route['web/records/(:any)/(:num)'] = 'web_records/detail/$1/$2';
$route['web/records/(:any)'] = 'web_records/index/$1';
