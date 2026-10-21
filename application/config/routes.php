
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Default Routes
|--------------------------------------------------------------------------
*/

// Default page displayed when the application root URL is accessed.
$route['default_controller'] = 'Dashboard/index';

// Controller used when no matching route is found.
$route['404_override'] = '';

// Keep dashes in URLs instead of converting them to underscores.
$route['translate_uri_dashes'] = FALSE;


/*
|--------------------------------------------------------------------------
| Authentication and Account Security
|--------------------------------------------------------------------------
*/

// Display the login page and process login credentials.
$route['login']['GET'] = 'Auth/index';
$route['login']['POST'] = 'Auth/login';

// End the current user's session.
$route['logout']['POST'] = 'Auth/logout';

// Display the password setup/change page and save the new password.
$route['change-password']['GET'] = 'Auth/setup';
$route['change-password']['POST'] = 'Auth/change_password';


/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

// Display the main dashboard.
$route['dashboard']['GET'] = 'Dashboard/index';


/*
|--------------------------------------------------------------------------
| Notifications and Pending Tasks
|--------------------------------------------------------------------------
*/

// View pending tasks and the notification inbox.
$route['notifications/tasks']['GET'] = 'Notifications/pending';
$route['notifications/inbox']['GET'] = 'Notifications/inbox';

// Claim a task or mark a notification as read.
$route['notifications/tasks/claim']['POST'] = 'Notifications/claim';
$route['notifications/inbox/read']['POST'] = 'Notifications/mark_read';


/*
|--------------------------------------------------------------------------
| Document Management
|--------------------------------------------------------------------------
*/

// Display a document section or category.
$route['documents/(:any)']['GET'] = 'Documents/index/$1';

// Add a softcopy document directly.
$route['documents/softcopy/direct']['POST'] = 'Documents/direct_softcopy';

// Save document changes or dispose of a document.
$route['documents/(:any)/save']['POST'] = 'Documents/save/$1';
$route['documents/(:any)/dispose']['POST'] = 'Documents/dispose/$1';


/*
|--------------------------------------------------------------------------
| File Management
|--------------------------------------------------------------------------
*/

// Upload files and access file-related operations.
$route['files/upload']['POST'] = 'Files/upload';

// Download, print, or view a file.
$route['files/download/(:num)']['GET'] = 'Files/download/$1';
$route['files/print/(:num)']['GET'] = 'Files/print_file/$1';
$route['files/print-content/(:num)']['GET'] = 'Files/print_content/$1';

// Review a file or access its review content.
$route['files/review/(:num)']['GET'] = 'Files/review/$1';
$route['files/review-content/(:num)/(:any)/(:any)']['GET'] = 'Files/review_content/$1/$2/$3';

// Configure office document viewing or printing.
$route['files/office-setup']['GET'] = 'Files/office_setup';


/*
|--------------------------------------------------------------------------
| Locations and Storage Places
|--------------------------------------------------------------------------
*/

// Display the places overview and individual place sections.
$route['places']['GET'] = 'Places/home';
$route['places/(:any)']['GET'] = 'Places/index/$1';

// Update or deactivate a place.
$route['places/(:any)/save']['POST'] = 'Places/save/$1';
$route['places/(:any)/deactivate']['POST'] = 'Places/deactivate/$1';


/*
|--------------------------------------------------------------------------
| Document Requests
|--------------------------------------------------------------------------
*/

// View and manage the current user's requests.
$route['my-requests/(:any)']['GET'] = 'Requests/mine/$1';
$route['my-requests/(:any)/save']['POST'] = 'Requests/save/$1';
$route['my-requests/(:any)/submit']['POST'] = 'Requests/submit/$1';
$route['my-requests/(:any)/cancel']['POST'] = 'Requests/cancel/$1';


/*
|--------------------------------------------------------------------------
| Assigned Tasks and Document Transfers
|--------------------------------------------------------------------------
*/

// View assigned tasks and submit workflow decisions.
$route['my-tasks/(:any)']['GET'] = 'Requests/tasks/$1';
$route['my-tasks/(:any)/decide']['POST'] = 'Requests/decide/$1';

// Dispatch or accept a physical document transfer.
$route['my-tasks/hardcopy-transfer/dispatch']['POST'] = 'Requests/dispatch_transfer';
$route['my-tasks/hardcopy-transfer/accept']['POST'] = 'Requests/accept_transfer';


/*
|--------------------------------------------------------------------------
| Document Assignments and Audit Logs
|--------------------------------------------------------------------------
*/

// View and manage document assignments.
$route['admin/document-assignments']['GET'] = 'Administration/document_assignments';

// View system audit records.
$route['admin/audit']['GET'] = 'Audit/index';

// Save document assignments or assign documents to a location/category.
$route['admin/document-assignments/save']['POST'] = 'Administration/save_document_assignment';
$route['admin/document-assignments/location']['POST'] = 'Administration/assign_location';
$route['admin/document-assignments/category']['POST'] = 'Administration/assign_category';


/*
|--------------------------------------------------------------------------
| Backup and Restore
|--------------------------------------------------------------------------
*/

// View available backups and create new backups.
$route['admin/backups']['GET'] = 'Backups/index';
$route['admin/backups/create']['POST'] = 'Backups/create';

// Remove old backups and import or restore backup data.
$route['admin/backups/cleanup']['POST'] = 'Backups/cleanup';
$route['admin/backups/import']['POST'] = 'Backups/import';
$route['admin/backups/restore']['POST'] = 'Backups/restore';

// Download a specific backup file.
$route['admin/backups/download/(:any)']['GET'] = 'Backups/download/$1';


/*
|--------------------------------------------------------------------------
| User Management
|--------------------------------------------------------------------------
*/

// View and manage system users.
$route['admin/users']['GET'] = 'Administration/users';

// View the documents assigned to a specific user.
$route['admin/users/(:num)/assigned-documents']['GET'] = 'Administration/user_assigned_documents/$1';

// Create, update, or deactivate a user.
$route['admin/users/save']['POST'] = 'Administration/save_user';
$route['admin/users/deactivate']['POST'] = 'Administration/deactivate_user';


/*
|--------------------------------------------------------------------------
| Role and Permission Management
|--------------------------------------------------------------------------
*/

// View and manage system roles.
$route['admin/roles']['GET'] = 'Administration/roles';

// Create, update, deactivate, or remove a role.
$route['admin/roles/save']['POST'] = 'Administration/save_role';
$route['admin/roles/deactivate']['POST'] = 'Administration/deactivate_role';
$route['admin/roles/remove']['POST'] = 'Administration/remove_role';


/*
|--------------------------------------------------------------------------
| Workflow Management
|--------------------------------------------------------------------------
*/

// View and manage document approval workflows.
$route['admin/workflows']['GET'] = 'Administration/workflows';

// Add, update, remove, or reorder workflow steps.
$route['admin/workflows/step/save']['POST'] = 'Administration/save_workflow_step';
$route['admin/workflows/step/remove']['POST'] = 'Administration/remove_workflow_step';
$route['admin/workflows/step/move']['POST'] = 'Administration/move_workflow_step';

// Publish a workflow or create a copy of an existing workflow.
$route['admin/workflows/publish']['POST'] = 'Administration/publish_workflow';
$route['admin/workflows/clone']['POST'] = 'Administration/clone_workflow';