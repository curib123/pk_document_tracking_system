<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__) . '/application/');
class MY_Controller {}
require APPPATH . 'controllers/Files.php';
$method = new ReflectionMethod(Files::class, 'safe_download_name');
$controller = (new ReflectionClass(Files::class))->newInstanceWithoutConstructor();
$cases = [
    ['../report.pdf', 'report.pdf'],
    ["C:" . chr(92) . "office" . chr(92) . "report.pdf", 'report.pdf'],
    ["unsafe" . chr(13) . chr(10) . "X-Test: yes.pdf", 'unsafe__X-Test: yes.pdf'],
    ['name"quote.pdf', 'name_quote.pdf'],
    ['', 'document']
];
foreach ($cases as [$incoming, $expected]) {
    $actual = $method->invoke($controller, $incoming);
    if ($actual !== $expected) {
        throw new RuntimeException('Unsafe attachment filename: ' . var_export($incoming, TRUE) .
            ', got ' . var_export($actual, TRUE));
    }
}
echo "File response filenames sanitize path separators, quotes and line breaks.\n";
