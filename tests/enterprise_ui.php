<?php
declare(strict_types=1);
$root = dirname(__DIR__);
define('BASEPATH', $root . '/');
define('APPPATH', $root . '/application/');

function ui_escape(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function site_url(string $path): string { return '/index.php/' . ltrim($path, '/'); }
function view_html(string $name, array $vars): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require APPPATH . 'views/web/' . $name . '.php';
    return (string) ob_get_clean();
}
require APPPATH . 'helpers/web_ui_helper.php';
require APPPATH . 'helpers/web_display_helper.php';
$cases = [];
$check = static function (string $name, bool $ok) use (&$cases): void { $cases[$name] = $ok; };

$modal = view_html('components/modal', [
    'modalId'=>'test-delete','modalTitle'=>'Delete?','modalTone'=>'danger',
    'modalMessage'=>'Delete <untrusted> record',
    'modalContentHtml'=>'<p>Authorized partial</p>',
    'modalAction'=>site_url('web/catalog/areas/remove'),
    'modalFields'=>['csrf'=>'session-token','id'=>8,'version'=>2],
    'modalSubmit'=>'Delete record','modalBack'=>site_url('web/catalog/areas'),
    'modalAutoOpen'=>true,
]);
$check('modal uses normal POST and CSRF', str_contains($modal,'method="post"') && str_contains($modal,'name="csrf"'));
$check('modal escapes displayed message', str_contains($modal,'&lt;untrusted&gt;') && !str_contains($modal,'Delete <untrusted>'));
$check('modal retains version and one common shell', str_contains($modal,'name="version"') && substr_count($modal,'modal-content')===1);

$select = view_html('components/searchable_select', [
    'selectId'=>'status','selectName'=>'active','selectLabel'=>'Status',
    'selectValue'=>'0','selectOptions'=>[
        ['value'=>'1','label'=>'Active'], ['value'=>'0','label'=>'Inactive']
    ],
    'selectRequired'=>false, 'selectPlaceholder'=>'All statuses',
]);
$check('inactive zero remains selected', str_contains($select,'value="0" selected'));
$check('searchable select has native HTML fallback', str_contains($select,'<select ') && str_contains($select,'data-pk-searchable'));

$definition = [
    'label'=>'Areas','columns'=>['name','active'],
    'fields'=>[
        ['name'=>'name','label'=>'Name','type'=>'text','required'=>true],
        ['name'=>'active','label'=>'Active','type'=>'checkbox','required'=>false],
    ],
];
$table = view_html('components/data_table', [
    'tableKind'=>'catalog','module'=>'areas','definition'=>$definition,
    'query'=>['q'=>'Warehouse','active'=>'0','sort'=>'name','direction'=>'asc'],
    'records'=>[
        'rows'=>[['id'=>5,'name'=>'Warehouse & Admin','active'=>0]],
        'total'=>1,'page'=>1,'pages'=>1,'limit'=>25
    ],
    'can_edit'=>true,'can_delete'=>true,
]);
$s = strpos($table,'Search records');
$f = strpos($table,'Filter results');
$t = strpos($table,'<table ');
$p = strpos($table,'Table pagination');
$check('table places search, filters, rows, pagination in order',
    $s!==false && $f!==false && $t!==false && $p!==false && $s<$f && $f<$t && $t<$p);
$check('table escapes database text', str_contains($table,'Warehouse &amp; Admin'));
$check('disabled pagination buttons not clickable', str_contains($table,'aria-disabled="true">Previous') && str_contains($table,'aria-disabled="true">Next'));
$check('row limit is below table', strpos($table,'Rows per page')>$t);
$check('request type filters query the model and remain in pagination',
    str_contains(file_get_contents(APPPATH . 'models/Read_model.php'), "'t.type'")
    && str_contains(file_get_contents(APPPATH . 'views/web/components/data_table.php'), "name="type"")
);

$form = view_html('catalog_form', [
    'module'=>'areas','definition'=>$definition,'page_title'=>'Edit Areas',
    'record'=>['id'=>5,'version'=>3,'name'=>'A <Draft>','active'=>0],
    'lookups'=>[],'csrf'=>'test-csrf','form_error'=>[
        'message'=>'Please check','fields'=>['name'=>'Name required']
    ],
]);
$check('catalog form uses common modal', str_contains($form,'id="pk-catalog-form-modal"'));
$check('catalog preserves escaped input', str_contains($form,'A &lt;Draft&gt;'));
$check('catalog displays field-level validation', str_contains($form,'Name required'));

$display = pk_web_display_fields([
    'id'=>3,'name'=>'Production','password_hash'=>'secret-hash',
    'area_id'=>4,'area'=>'Factory','payload'=>['secret'=>'private']
]);
$displayJson = json_encode($display);
$check('details hide IDs and secrets', !str_contains($displayJson,'secret-hash') && !str_contains($displayJson,'private') && !str_contains($displayJson,'area_id'));
$check('details show related names', str_contains($displayJson,'Factory'));

$header = file_get_contents(APPPATH . 'views/web/header.php');
$footer = file_get_contents(APPPATH . 'views/web/footer.php');
$js = file_get_contents($root . '/public/assets/js/enterprise-ui.js');
$css = file_get_contents($root . '/public/assets/css/enterprise-ui.css');
$controller = file_get_contents(APPPATH . 'modules/catalog/controllers/Catalog_controller.php');
$check('keyboard skip navigation exists', str_contains($header,'Skip to content'));
$check('sidebar is one shared template', substr_count($header,'components/sidebar.php')===2);
$check('Font Awesome falls back to Bootstrap Icons', str_contains($header,'font-awesome') && str_contains($header,'bootstrap-icons'));
$check('Roboto falls back to offline system fonts', str_contains($css,'"Segoe UI"'));
$check('jQuery loads before enterprise script', strpos($footer,'jquery-3.7.1')<strpos($footer,'enterprise-ui.js'));
$check('jQuery performs no REST/AJAX calls', !preg_match('/\$\.(ajax|getJSON|post)\s*\(|\bfetch\s*\(|XMLHttpRequest/', $js));
$check('service owns catalog mutation', str_contains($controller,'new Catalog_service($context)') && str_contains($controller,'->transaction('));
$check('full list search remains normal CSRF POST', str_contains($controller,'function lookup(') && str_contains($controller,'$this->webPost()'));

$failed = 0;
foreach ($cases as $name=>$ok) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $failed++;
}
echo count($cases) . " enterprise UI checks, $failed failures\n";
exit($failed ? 1 : 0);
