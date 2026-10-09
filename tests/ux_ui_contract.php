<?php
/** Shared component UX regression tests against the current CI3 templates. */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

function site_url(string $path = ''): string {
    return '/' . ltrim($path, '/');
}

function html_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function verify(bool $condition, string $message): void {
    global $errors;
    if (!$condition) $errors[] = $message;
}

class FakeLoader {
    public function view(string $path, array $params = []): void {
        extract($params);
        include dirname(__DIR__) . '/application/view/' . $path . '.php';
    }
}

class FakeRenderer {
    public $load;

    public function __construct() {
        $this->load = new FakeLoader();
    }

    public function render(array $params): string {
        extract($params);
        ob_start();
        include dirname(__DIR__) . '/application/view/reusable_components/reusable_datatable.php';
        return (string)ob_get_clean();
    }
}

function table_fixture(bool $empty = false, bool $grid = false): string {
    $dt_columns = ['title'=>'Title', 'status'=>'Status'];
    $dt_rows = $empty ? [] : [[
        'id'=>7,
        'cells'=>['title'=>'<Example Document>', 'status'=>'active'],
        'display'=>['Title'=>'<Example Document>', 'Status'=>'active'],
        'record'=>['id'=>7],
        'buttons'=>[['type'=>'view'],['type'=>'steps']]
    ]];
    $vars = [
        'dt_columns'=>$dt_columns, 'dt_rows'=>$dt_rows,
        'dt_path'=>'admin/workflows', 'dt_q'=>$empty?'unknown':'',
        'dt_filter'=>'', 'dt_page'=>1, 'dt_limit'=>10,
        'dt_total'=>$empty?0:1, 'dt_sort'=>'title', 'dt_dir'=>'asc',
        'dt_sortable'=>['title'], 'dt_filters'=>['status'=>[''=>'All Statuses','active'=>'Active']],
        'dt_filter_values'=>['status'=>''], 'dt_badges'=>['status'],
        'dt_create'=>'', 'dt_extra_params'=>['layout'=>$grid?'grid':'table']
    ];
    return (new FakeRenderer())->render($vars);
}

$table = table_fixture();
verify(substr_count($table, 'tabindex="0" role="button"') === 1,
    'Viewable record should expose just one keyboard-operated cell.');
verify(strpos($table, 'data-row-view=') !== false,
    'Row details must retain their data payload.');
verify(strpos($table, '&lt;Example Document&gt;') !== false,
    'Document titles must be HTML-escaped.');
verify(strpos($table, 'aria-sort="ascending"') !== false,
    'Sort direction must be announced.');
verify(strpos($table, 'Step Actions') !== false &&
    strpos($table, 'workflowStepsModal-7') !== false,
    'Approval steps must launch the current workflow modal.');
verify(strpos($table, 'Previous page unavailable') !== false &&
    strpos($table, 'Next page unavailable') !== false,
    'Inactive page arrows must not be clickable.');

$empty = table_fixture(true);
verify(strpos($empty, 'No matching records') !== false &&
    strpos($empty, 'Try another search') !== false,
    'Empty filtered tables must explain how to recover.');
$grid = table_fixture(false, true);
verify(strpos($grid, 'data-record-layout="grid"') !== false &&
    strpos($grid, 'workflowStepsModal-7') !== false,
    'Document grid must preserve its record action buttons.');

$sidebar = file_get_contents($root.'/application/view/layout/sidebar_top_nav.php');
$js = file_get_contents($root.'/public/assets/js/app.js');
$css = file_get_contents($root.'/public/assets/css/app.css');
$workflow = file_get_contents($root.'/application/view/pages/workflow_builder/index.php');
$recordActions = file_get_contents($root.'/application/view/reusable_components/record_actions.php');

foreach (['id="sidebarBackdrop"', 'id="sidebarClose"',
    'aria-controls="appSidebar"', 'aria-expanded="false"',
    'href="#mainContent"', 'id="mainContent"'] as $item) {
    verify(strpos($sidebar,$item)!==false,'Navigation missing: '.$item);
}
foreach (['setupSearchableSelects', 'syncSearchableControls',
    'aria-activedescendant', 'positionList',
    'function setSidebar(', 'modalFocusables', 'renderRecordDetails'] as $part) {
    verify(strpos($js,$part)!==false,'Shared JS missing: '.$part);
}
foreach (['.searchable-options','.searchable-native','.sidebar-backdrop',
    '.skip-link',':focus-visible','font-family: \'Roboto\'',
    '.auth-required-dialog'] as $rule) {
    verify(strpos($css,$rule)!==false,'Shared CSS missing: '.$rule);
}
verify(strpos($workflow,"'type'=>'steps'")!==false &&
    strpos($recordActions,'Step Actions')!==false &&
    strpos($workflow,'workflowStepsModal-')!==false,
    'Published and draft approval-step modals must remain accessible.');

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL,$errors).PHP_EOL);
    exit(1);
}
echo "Enterprise UI component and accessibility contract passed.\n";
