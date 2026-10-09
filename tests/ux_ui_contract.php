<?php
/**
 * Lightweight shared-component regression checks.
 * These tests render the actual CI3 table view with fixture data, without a DB.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

function site_url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function html_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function verify(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) $errors[] = $message;
}

function table_fixture(bool $empty = false): string
{
    global $root;
    $dt_columns = ['title' => 'Title', 'status' => 'Status'];
    $dt_rows = $empty ? [] : [[
        'id' => 7,
        'cells' => ['title' => '<Example Document>', 'status' => 'active'],
        'display' => ['Title' => '<Example Document>', 'Status' => 'active'],
        'record' => ['id' => 7],
        'buttons' => [['type' => 'view'], ['type' => 'workflow']]
    ]];
    $dt_path = 'admin/workflows';
    $dt_q = $empty ? 'unknown' : '';
    $dt_filter = '';
    $dt_page = 1;
    $dt_limit = 10;
    $dt_total = $empty ? 0 : 1;
    $dt_sort = 'title';
    $dt_dir = 'asc';
    $dt_sortable = ['title'];
    $dt_filters = ['status' => ['' => 'All Statuses', 'active' => 'Active']];
    $dt_filter_values = ['status' => ''];
    $dt_badges = ['status'];
    $dt_create = '';
    ob_start();
    include $root . '/application/view/reusable_components/reusable_datatable.php';
    return (string) ob_get_clean();
}

$rendered = table_fixture();
verify(substr_count($rendered, 'tabindex="0" role="button"') === 1,
    'A viewable row should have one keyboard cell target, not one per cell.');
verify(strpos($rendered, 'data-row-view=') !== false,
    'Table view must keep the existing safe detail payload.');
verify(strpos($rendered, '&lt;Example Document&gt;') !== false,
    'Untrusted document titles must be HTML-escaped.');
verify(strpos($rendered, 'aria-sort="ascending"') !== false,
    'Sortable headers must expose the sort direction.');
verify(strpos($rendered, 'Step Actions') !== false &&
    strpos($rendered, 'data-workflow-target="wf-7"') !== false,
    'Workflow table must link directly to the approval step editor.');
verify(strpos($rendered, 'Previous page unavailable') !== false &&
    strpos($rendered, 'Next page unavailable') !== false,
    'Unavailable pagination controls must not be actionable links.');

$empty = table_fixture(true);
verify(strpos($empty, 'No matching records') !== false,
    'Filtered empty tables should explain the empty state.');
verify(strpos($empty, 'Try another search') !== false,
    'Filtered empty tables should suggest how to recover.');

$sidebar = file_get_contents($root . '/application/view/layout/sidebar_top_nav.php');
$js = file_get_contents($root . '/public/assets/js/app.js');
$css = file_get_contents($root . '/public/assets/css/app.css');
$workflow = file_get_contents($root . '/application/view/pages/workflow_builder/index.php');

foreach (['id="sidebarBackdrop"', 'aria-controls="appSidebar"',
    'aria-expanded="false"', 'href="#mainContent"', 'id="mainContent"'] as $item) {
    verify(strpos($sidebar, $item) !== false, 'Navigation missing: ' . $item);
}
foreach (['setupSearchableSelects', 'syncSearchableControls',
    'aria-activedescendant', 'role', 'combobox',
    'positionList', 'setSidebarOpen', 'modalFocusables'] as $item) {
    verify(strpos($js, $item) !== false, 'Shared frontend behavior missing: ' . $item);
}
foreach (['.searchable-options', '.searchable-native', '.sidebar-backdrop',
    '.skip-link', ':focus-visible', 'font-family: \'Roboto\''] as $item) {
    verify(strpos($css, $item) !== false, 'Design-system style missing: ' . $item);
}
verify(strpos($workflow, "'type'=>'workflow'") !== false,
    'Workflow pages must expose their Step Actions link.');

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "Enterprise UI component and accessibility contract passed.\n";
