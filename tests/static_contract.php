<?php
// Tiny framework-free architectural check. Not a substitute for MySQL integration tests.
$root = dirname(__DIR__);
$required = [
    'application/bootstrap.php', 'application/config/routes.php',
    'application/core/MY_Controller.php', 'application/libraries/Table_pager.php',
    'application/controllers/Auth.php', 'application/controllers/Dashboard.php',
    'application/controllers/Records.php', 'application/controllers/Requests.php',
    'application/controllers/Administration.php', 'application/models/Permission_model.php',
    'application/models/Records_model.php', 'application/models/Request_model.php',
    'application/models/Administration_model.php', 'application/models/Document_file_model.php',
    'application/services/records/Document_file_service.php', 'application/services/records/Records_service.php',
    'application/services/request/request_service.php', 'application/services/administration/Administration_service.php',
    'application/view/layout/header.php', 'application/view/layout/footer.php',
    'application/view/layout/sidebar_top_nav.php', 'application/view/components/datatable.php',
    'application/view/pages/authentication/index.php', 'application/view/pages/dashboard/index.php',
    'application/view/pages/records/index.php', 'application/view/pages/request/index.php',
    'application/view/pages/administration/users.php', 'application/view/pages/administration/roles.php',
    'application/view/pages/administration/workflows.php', 'public/assets/css/app.css',
    'public/assets/js/app.js', 'database/schema.sql',
    'database/migrations/20261009_document_workflows.sql', 'database/seed.sql',
    'tools/audit_legacy_schema.php', 'tests/business_flows.sh', 'tests/pagination_flows.sh'
];
$failed = [];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) === 0) $failed[] = 'Missing: ' . $file;
}
$routes = file_get_contents($root . '/application/config/routes.php');
foreach (['documents/', 'places/', 'my-requests/', 'my-tasks/', 'admin/users',
    'admin/roles', 'admin/workflows', 'admin/workflows/clone',
    'documents/softcopy/upload', 'documents/softcopy/files/', 'change-password'] as $name) {
    if (strpos($routes, $name) === false) $failed[] = 'Missing route: ' . $name;
}
$core = file_get_contents($root . '/application/core/MY_Controller.php');
if (strpos($core, 'require_permission') === false) $failed[] = 'Missing server permission gate';
$config = file_get_contents($root . '/application/config/config.php');
if (strpos($config, "'csrf_protection'] = TRUE") === false) $failed[] = 'CSRF protection must be enabled';
$js = file_get_contents($root . '/public/assets/js/app.js');
if (preg_match('/\bfetch\s*\(|\$\.ajax\s*\(/', $js)) $failed[] = 'Unexpected REST/AJAX operation';
$seed = file_get_contents($root . '/database/seed.sql');
foreach (['staff','plant_manager','document_control_officer','internal_audit','super_admin'] as $role) {
    if (strpos($seed, "'" . $role . "'") === false) $failed[] = 'Missing base role ' . $role;
}
$pager = file_get_contents($root . '/application/libraries/Table_pager.php');
if (strpos($pager, '[10, 25, 50, 100]') === FALSE || strpos($pager, 'function clamp(') === FALSE) {
    $failed[] = 'Shared page/limit validation missing.';
}
foreach (['Requests.php', 'Administration.php', 'Records.php'] as $controller) {
    $code = file_get_contents($root . '/application/controllers/' . $controller);
    if (strpos($code, 'array_slice(') !== FALSE || strpos($code, 'count($rows)') !== FALSE) {
        $failed[] = 'PHP in-memory table pagination found in ' . $controller;
    }
}
foreach ([
    'Request_model.php' => ['count_listing', 'page_listing'],
    'Administration_model.php' => ['count_users', 'page_users', 'count_roles', 'page_roles',
        'count_workflows', 'page_workflows'],
    'Records_model.php' => ['count_documents', 'page_documents', 'count_places', 'page_places']
] as $model => $methods) {
    $code = file_get_contents($root . '/application/models/' . $model);
    foreach ($methods as $method) {
        if (strpos($code, 'function ' . $method . '(') === FALSE) {
            $failed[] = 'Missing SQL pagination method ' . $model . '::' . $method;
        }
    }
}
if ($failed) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}
echo "Static contracts passed (" . count($required) . " required files)." . PHP_EOL;
