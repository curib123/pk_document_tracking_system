<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$bridge = file_get_contents($root . '/application/controllers/Web_dashboard.php');
$module = file_get_contents($root . '/application/modules/dashboard/controllers/Dashboard_controller.php');
$base = file_get_contents($root . '/application/core/MY_Web_Controller.php');
$view = file_get_contents($root . '/application/modules/dashboard/views/index.php');
$cases = [
    'dashboard bridge extends modular controller' => str_contains($bridge, 'extends Dashboard_controller'),
    'module uses browser controller' => str_contains($module, 'extends MY_Web_Controller'),
    'module renders its own view' => str_contains($module, "modules/dashboard/views/index"),
    'module uses permission-scoped dashboard data' => str_contains($module, "can('dashboard.view')"),
    'module has no API endpoint calls' => !str_contains($module, 'endpoint('),
    'web view supports qualified module view names' => str_contains($base, "str_starts_with($view, 'modules/')"),
    'dashboard escapes rendered label' => str_contains($view, 'ui_escape($title)'),
];
$failed = 0;
foreach ($cases as $name => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        ++$failed;
    }
}
exit($failed ? 1 : 0);
