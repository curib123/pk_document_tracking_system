<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$route['default_controller'] = 'Dashboard/index';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['login']['GET'] = 'Auth/index';
$route['login']['POST'] = 'Auth/login';
$route['logout']['POST'] = 'Auth/logout';
$route['change-password']['GET'] = 'Auth/setup';
$route['change-password']['POST'] = 'Auth/change_password';

$route['dashboard']['GET'] = 'Dashboard/index';
$route['documents/(:any)']['GET'] = 'Documents/index/$1';
$route['documents/softcopy/direct']['POST'] = 'Documents/direct_softcopy';
$route['documents/(:any)/save']['POST'] = 'Documents/save/$1';
$route['documents/(:any)/dispose']['POST'] = 'Documents/dispose/$1';
$route['files/upload']['POST'] = 'Files/upload';
$route['files/download/(:num)']['GET'] = 'Files/download/$1';
$route['files/review/(:num)']['GET'] = 'Files/review/$1';
$route['places']['GET'] = 'Places/home';
$route['places/(:any)']['GET'] = 'Places/index/$1';
$route['places/(:any)/save']['POST'] = 'Places/save/$1';
$route['places/(:any)/deactivate']['POST'] = 'Places/deactivate/$1';

$route['my-requests/(:any)']['GET'] = 'Requests/mine/$1';
$route['my-requests/(:any)/save']['POST'] = 'Requests/save/$1';
$route['my-requests/(:any)/submit']['POST'] = 'Requests/submit/$1';
$route['my-requests/(:any)/cancel']['POST'] = 'Requests/cancel/$1';
$route['my-tasks/(:any)']['GET'] = 'Requests/tasks/$1';
$route['my-tasks/(:any)/decide']['POST'] = 'Requests/decide/$1';
$route['my-tasks/hardcopy-transfer/dispatch']['POST'] = 'Requests/dispatch_transfer';
$route['my-tasks/hardcopy-transfer/accept']['POST'] = 'Requests/accept_transfer';

$route['admin/document-assignments']['GET'] = 'Administration/document_assignments';
$route['admin/document-assignments/save']['POST'] = 'Administration/save_document_assignment';
$route['admin/users']['GET'] = 'Administration/users';
$route['admin/users/save']['POST'] = 'Administration/save_user';
$route['admin/users/deactivate']['POST'] = 'Administration/deactivate_user';
$route['admin/roles']['GET'] = 'Administration/roles';
$route['admin/roles/save']['POST'] = 'Administration/save_role';
$route['admin/workflows']['GET'] = 'Administration/workflows';
$route['admin/workflows/step/save']['POST'] = 'Administration/save_workflow_step';
$route['admin/workflows/step/remove']['POST'] = 'Administration/remove_workflow_step';
$route['admin/workflows/step/move']['POST'] = 'Administration/move_workflow_step';
$route['admin/workflows/publish']['POST'] = 'Administration/publish_workflow';
$route['admin/workflows/clone']['POST'] = 'Administration/clone_workflow';
