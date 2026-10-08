<?php
declare(strict_types=1);

// Dependency-free checks for incremental server-rendered MVC migration.
$root = dirname(__DIR__);
$routes = file_get_contents($root . '/application/config/routes.php');
$header = file_get_contents($root . '/application/views/web/header.php');
$catalog = file_get_contents($root . '/application/views/web/catalog_form.php');
$controllers = ['Web_auth', 'Web_dashboard', 'Web_catalog', 'Web_records'];
$cases = [
    'native dashboard route exists' => str_contains($routes, "'web'] = 'web_dashboard/index'"),
    'native POST route exists' => str_contains($routes, "'web/catalog/(:any)/save'"),
    'native forms use shared modal CSRF' => str_contains($catalog, "'csrf' => $csrf") && str_contains(file_get_contents($root . '/application/views/web/components/modal.php'), 'name="<?= ui_escape($key) ?>"'),
    'optimistic version is passed' => str_contains($catalog, "$modalFields['version']"),
    'bootstrap stylesheet used' => str_contains($header, 'bootstrap@5.3.8'),
    'shared enterprise stylesheet present' => str_contains($header, 'assets/css/enterprise-ui.css'),
    'single enterprise enhancement script present' => str_contains(file_get_contents($root . '/application/views/web/footer.php'), 'enterprise-ui.js'),
];
foreach ($controllers as $name) {
    $content = file_get_contents($root . '/application/controllers/' . $name . '.php');
    $moduleClass = ['Web_dashboard' => 'Dashboard_controller', 'Web_auth' => 'Auth_controller', 'Web_catalog' => 'Catalog_controller', 'Web_records' => 'Records_controller'][$name];
    $cases[$name . ' delegates to feature controller'] = str_contains($content, 'extends ' . $moduleClass);
    $cases[$name . ' does not dispatch JSON endpoints'] = !str_contains($content, '$this->endpoint(');
}
$failures = 0;
foreach ($cases as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$passed) {
        ++$failures;
    }
}
exit($failures ? 1 : 0);
