<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'Dashboard';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['login']['GET'] = 'Auth/index';
$route['login']['POST'] = 'Auth/login';
$route['logout']['POST'] = 'Auth/logout';
$route['change-password']['POST'] = 'Auth/change_password';

$route['dashboard']['GET'] = 'Dashboard/index';
$route['documents/(:any)']['GET'] = 'Records/documents/$1';
$route['documents/(:any)/save']['POST'] = 'Records/save_document/$1';
$route['documents/(:any)/delete']['POST'] = 'Records/delete_document/$1';
$route['documents/softcopy/upload']['POST'] = 'Records/upload_document';
$route['documents/softcopy/grant/revoke']['POST'] = 'Records/revoke_access';
$route['documents/softcopy/files/(:num)']['GET'] = 'Records/download_document/$1';
$route['places/(:any)']['GET'] = 'Records/places/$1';
$route['places/(:any)/save']['POST'] = 'Records/save_place/$1';
$route['places/(:any)/delete']['POST'] = 'Records/delete_place/$1';
$route['my-requests/(:any)']['GET'] = 'Requests/mine/$1';
$route['my-requests/(:any)/save']['POST'] = 'Requests/save/$1';
$route['my-requests/(:any)/submit']['POST'] = 'Requests/submit/$1';
$route['my-requests/(:any)/delete']['POST'] = 'Requests/delete/$1';
$route['my-tasks/(:any)']['GET'] = 'Requests/tasks/$1';
$route['my-tasks/(:any)/decide']['POST'] = 'Requests/decide/$1';
$route['admin/users']['GET'] = 'Administration/users';
$route['admin/users/save']['POST'] = 'Administration/save_user';
$route['admin/users/delete']['POST'] = 'Administration/delete_user';
$route['admin/roles']['GET'] = 'Administration/roles';
$route['admin/roles/save']['POST'] = 'Administration/save_role';
$route['admin/workflows']['GET'] = 'Administration/workflows';
$route['admin/workflows/save']['POST'] = 'Administration/save_workflow';
$route['admin/workflows/clone']['POST'] = 'Administration/clone_workflow';
$route['admin/workflows/step']['POST'] = 'Administration/save_step';
$route['admin/workflows/step/delete']['POST'] = 'Administration/delete_step';
