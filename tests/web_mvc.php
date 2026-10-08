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
    'native forms use CSRF' => str_contains($catalog, 'name="csrf"'),
    'optimistic version is passed' => str_contains($catalog, 'name="version"'),
    'bootstrap stylesheet used' => str_contains($header, 'bootstrap@5.3.8'),
    'no custom stylesheet on native pages' => !str_contains($header, 'assets/css/'),
    'no JavaScript bundle on native pages' => !str_contains($header, '<script'),
];
foreach ($controllers as $name) {
    $content = file_get_contents($root . '/application/controllers/' . $name . '.php');
    $cases[$name . ' uses browser MVC'] = $name === 'Web_dashboard'
        ? str_contains($content, 'extends Dashboard_controller')
        : str_contains($content, 'extends MY_Web_Controller');
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
