<?php
declare(strict_types=1);

/** Static architectural contracts: real HTTP behavior is tested separately. */
$root = dirname(__DIR__);
$get = static fn(string $path): string =>
    (string) file_get_contents($root . '/' . $path);

$base = $get('application/core/MY_Controller.php');
$web = $get('application/core/MY_Web_Controller.php');
$authService = $get('application/libraries/Auth_service.php');
$gateway = $get('application/libraries/Http_gateway.php');
$config = $get('application/config/config.php');
$catalog = $get('application/modules/catalog/controllers/Catalog_controller.php');
$records = $get('application/modules/records/controllers/Records_controller.php');
$dashboard = $get('application/modules/dashboard/controllers/Dashboard_controller.php');
$auth = $get('application/modules/auth/controllers/Auth_controller.php');
$checks = [
    'CI3 session loaded by MY_Controller' =>
        str_contains($base, "load->library('session')"),
    'session stored outside public web root' =>
        str_contains($config, "PK_ROOT . '/storage/sessions'"),
    'private session files exist' =>
        is_file($root . '/storage/sessions/.gitkeep'),
    'CI3 cookie is explicitly named' =>
        str_contains($config, "sess_cookie_name'] = 'pk_dts_ci_session'"),
    'session cookie is strict SameSite' =>
        str_contains($config, "sess_samesite'] = 'Strict'"),
    'session restoration centrally checks version' =>
        str_contains($base, "session->userdata('session_version')"),
    'session restoration checks idle timeout' =>
        str_contains($base, "time() - $lastSeen > 1800"),
    'role checks use centralized require_permission' =>
        str_contains($base, 'function require_permission(') &&
        str_contains($base, '$current->require($permission)'),
    'central CSRF validation uses CI3 session' =>
        str_contains($base, "session->userdata('csrf')") &&
        str_contains($base, 'Security::csrf('),
    'authentication regenerates CI3 session identifiers' =>
        str_contains($base, 'session->sess_regenerate(true)'),
    'native web views read CI session tokens' =>
        str_contains($web, "session->userdata('csrf')"),
    'native browser flashes use CI3 flashdata' =>
        str_contains($web, "session->set_flashdata('web_flash'") &&
        str_contains($web, "session->flashdata('web_flash'"),
    'no raw PHP sessions in browser controller' =>
        !str_contains($web, '$_SESSION') && !str_contains($base, '$_SESSION'),
    'authentication service no longer mutates sessions' =>
        !str_contains($authService, '$_SESSION') &&
        !str_contains($authService, 'session_regenerate_id('),
    'legacy gateway delegates login to MY_Controller after DB success' =>
        str_contains($gateway, "completeAuthSession($operation)") &&
        str_contains($gateway, '_complete_auth_session('),
    'native login commits CI3 session after auth service' =>
        str_contains($auth, "_complete_auth_session('auth.login'"),
    'native password rotation uses central session method' =>
        str_contains($auth, "_complete_auth_session('auth.password'"),
    'native logout invalidates central session' =>
        str_contains($auth, "_complete_auth_session('auth.logout'"),
    'catalog index requires view permission' =>
        str_contains($catalog, "require_permission($module . '.view'"),
    'catalog form and lookup require edit/add permission' =>
        substr_count($catalog, "'edit' : 'add'") >= 2 &&
        substr_count($catalog, 'require_permission(') >= 5,
    'catalog delete requires delete permission' =>
        str_contains($catalog, "require_permission($module . '.delete'"),
    'catalog drafts use CI3 userdata' =>
        str_contains($catalog, "session->set_userdata('pk_catalog_draft'") &&
        !str_contains($catalog, '$_SESSION'),
    'record index and detail require permissions' =>
        substr_count($records, "require_permission($definition['permission']") === 2,
    'dashboard index requires explicit dashboard permission' =>
        str_contains($dashboard, "require_permission('dashboard.view'"),
    'domain service still owns catalog writes' =>
        str_contains($catalog, 'new Catalog_service($context)'),
];
$failures = 0;
foreach ($checks as $label => $passes) {
    echo ($passes ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$passes) ++$failures;
}
echo count($checks) . " permission/session contracts; $failures failures\n";
exit($failures ? 1 : 0);
