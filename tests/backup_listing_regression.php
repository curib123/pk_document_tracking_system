<?php
// Run: php tests/backup_listing_regression.php
define('BASEPATH', __DIR__);
require __DIR__ . '/../application/services/backup/backup_listing.php';

function assert_same($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$items = [
    ['name' => 'older.zip', 'kind' => 'Manual', 'size' => 100, 'created_at' => 10],
    ['name' => 'newer.zip', 'kind' => 'Automatic', 'size' => 200, 'created_at' => 20],
    ['name' => 'latest.zip', 'kind' => 'Manual', 'size' => 300, 'created_at' => 30],
];
$state = ['q' => '', 'type' => '', 'sort' => 'created_at', 'dir' => 'DESC', 'page' => 10, 'limit' => 2];
$result = Backup_listing::page($items, $state);
assert_same(2, $result['page'], 'Page clamping');
assert_same(3, $result['total'], 'Total');
assert_same('older.zip', $result['rows'][0]['name'], 'Last page item');
$state['page'] = 1;
$state['type'] = 'Manual';
$state['q'] = 'LATEST';
$result = Backup_listing::page($items, $state);
assert_same(1, $result['total'], 'Case insensitive search and type');
assert_same('latest.zip', $result['rows'][0]['name'], 'Filtered item');
$state['limit'] = 0;
$result = Backup_listing::page($items, $state);
assert_same(1, count($result['rows']), 'Zero limit is clamped');
echo "Backup listing regression checks passed.\n";
