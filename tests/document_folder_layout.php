<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require_once __DIR__.'/../application/services/presentation/folder_tree.php';
require_once __DIR__.'/../application/services/presentation/query_state.php';
require_once __DIR__.'/../application/services/presentation/document_folder_tree.php';

function assertFolder(bool $passed, string $message): void {
    if (!$passed) throw new RuntimeException($message);
}
$hardcopy=[
    ['key'=>'area:1','parent'=>'','name'=>'Production','kind'=>'area'],
    ['key'=>'specific:2','parent'=>'area:1','name'=>'Building A','kind'=>'specific'],
    ['key'=>'asset:3','parent'=>'specific:2','name'=>'Cabinet B','kind'=>'asset'],
    ['key'=>'location:4','parent'=>'asset:3','name'=>'Shelf 7','kind'=>'location']
];
$tree=Folder_tree::build($hardcopy);
assertFolder($tree['roots']===['area:1'],'Area must be first root level');
assertFolder($tree['nodes']['area:1']['children']===['specific:2'],'Area must open specific');
assertFolder($tree['nodes']['specific:2']['children']===['asset:3'],'Specific must open Asset');
assertFolder($tree['nodes']['asset:3']['children']===['location:4'],'Asset must contain Locations');
assertFolder($tree['nodes']['location:4']['children']===[],'Location is terminal folder');

$softcopy=Folder_tree::build([
    ['key'=>'category:7','parent'=>'','name'=>'Policies','kind'=>'category'],
    ['key'=>'category:8','parent'=>'category:7','name'=>'Production','kind'=>'category'],
    ['key'=>'category:9','parent'=>'category:8','name'=>'Controlled SOPs','kind'=>'category']
]);
assertFolder($softcopy['roots']===['category:7'],'Category must be the root');
assertFolder($softcopy['nodes']['category:7']['children']===['category:8'],'Subcategory must be navigable');
assertFolder($softcopy['nodes']['category:8']['children']===['category:9'],'Nested category folders must remain navigable');

$cycle=Folder_tree::build([
    ['key'=>'category:20','parent'=>'category:21','name'=>'Loop A','kind'=>'category'],
    ['key'=>'category:21','parent'=>'category:20','name'=>'Loop B','kind'=>'category']
]);
assertFolder($cycle['roots']===[] && $cycle['unavailable']===2,'Cycles must not appear as folders');

$input=[
 'layout'=>'folder','q'=>'sample','status'=>'active','page'=>'2','limit'=>'25',
 'sort'=>'title','dir'=>'asc'
];
$state=Query_state::parse($input,'updated_at');
assertFolder($state['layout']==='folder','Folder mode must survive normalized query state');
assertFolder($state['q']==='sample' && $state['status']==='active' &&
 $state['page']===2 && $state['limit']===25 && $state['sort']==='title' && $state['dir']==='ASC',
 'Folder mode must preserve the same search, filters, pagination and sort as table mode');
assertFolder(Query_state::parse(['layout'=>'table'])['layout']==='table','Table remains a separate mode');
assertFolder(Query_state::parse(['layout'=>'grid'])['layout']==='grid','Existing grid users are unchanged');
assertFolder(Query_state::parse(['layout'=>'invalid'])['layout']==='table','Unknown layouts are rejected');

$root=dirname(__DIR__);
$documentView=file_get_contents($root.'/application/view/pages/documents/shared_index.php');
$sharedTable=file_get_contents($root.'/application/view/reusable_components/reusable_datatable.php');
$folderView=file_get_contents($root.'/application/view/reusable_components/document_folder_list.php');
$folderModel=file_get_contents($root.'/application/models/Folder_model.php');
assertFolder(strpos($documentView,'document-layout-switch')!==FALSE,'System Documents must have layout toggle');
assertFolder(strpos($documentView, "http_build_query(['layout'=>\$activeLayout])")!==FALSE,
    'Switching Hardcopy and Softcopy tabs must keep the selected layout');
assertFolder(strpos($documentView, "\$activeLayout==='table'")!==FALSE &&
    strpos($documentView, "\$activeLayout==='folder'")!==FALSE,'Both table and folder links must render');
assertFolder(strpos($sharedTable, "if (\$dt_layout==='folder')")!==FALSE &&
    strpos($sharedTable,"elseif (\$dt_layout==='grid')")!==FALSE,
    'Exactly one layout branch must be rendered, preserving other grid callers');
assertFolder(strpos($folderView,"'layout'=>'folder'")!==FALSE,
    'Folder navigation must stay in folder mode');
assertFolder(strpos($folderView,"record_actions")!==FALSE &&
    strpos($folderView,"\$dt_row['display']")!==FALSE,
    'Folder rows must reuse same actions and permission-scoped document details');
assertFolder(strpos($folderView,"\$dt_filter_values")!==FALSE &&
    strpos($folderView,"\$dt_q")!==FALSE,
    'Folder links must preserve current search and filters');
assertFolder(strpos($folderView,"\$dt_columns")!==FALSE,
    'Folder list must include the same visible metadata columns as table mode');
assertFolder(strpos($folderModel,"\$folder==='all'")!==FALSE,
    'All Documents must remain navigable for documents without assigned locations');
assertFolder(strpos($folderView,"\$browser['breadcrumbs']")!==FALSE,
    'Folder list must show parent navigation');

// Multiple sibling Areas, Specifics, Assets and Locations must stay visible,
// and each document belongs under its deepest real folder assignment.
$multi=Folder_tree::build([
 ['key'=>'area:1','parent'=>'','name'=>'Area 01','kind'=>'area'],
 ['key'=>'area:2','parent'=>'','name'=>'Area 02','kind'=>'area'],
 ['key'=>'specific:10','parent'=>'area:1','name'=>'Specific 01','kind'=>'specific'],
 ['key'=>'specific:11','parent'=>'area:1','name'=>'Specific 02','kind'=>'specific'],
 ['key'=>'asset:100','parent'=>'specific:10','name'=>'Asset 001','kind'=>'asset'],
 ['key'=>'asset:101','parent'=>'specific:10','name'=>'Asset 002','kind'=>'asset'],
 ['key'=>'location:201','parent'=>'asset:100','name'=>'Location A','kind'=>'location'],
 ['key'=>'location:202','parent'=>'asset:100','name'=>'Location B','kind'=>'location'],
 ['key'=>'location:203','parent'=>'specific:11','name'=>'No Asset Location','kind'=>'location']
]);
assertFolder($multi['roots']===['area:1','area:2'],'Multiple Areas must remain siblings');
assertFolder(count($multi['nodes']['specific:10']['children'])===2,
    'One Specific must contain multiple Asset folders');
assertFolder(count($multi['nodes']['asset:100']['children'])===2,
    'One Asset must contain multiple Location folders');

$rows=[
 ['id'=>1,'record_path'=>['area_id'=>1,'specific_id'=>10,'asset_id'=>100,'location_id'=>201]],
 ['id'=>2,'record_path'=>['area_id'=>1,'specific_id'=>10,'asset_id'=>100,'location_id'=>201]],
 ['id'=>3,'record_path'=>['area_id'=>1,'specific_id'=>10,'asset_id'=>100,'location_id'=>202]],
 ['id'=>4,'record_path'=>['area_id'=>1,'specific_id'=>11,'asset_id'=>NULL,'location_id'=>203]],
 ['id'=>5,'record_path'=>['area_id'=>2,'specific_id'=>0,'asset_id'=>NULL,'location_id'=>NULL]],
 ['id'=>6,'record_path'=>['area_id'=>NULL,'specific_id'=>NULL,'asset_id'=>NULL,'location_id'=>NULL]]
];
$distribution=Document_folder_tree::distribute($rows,$multi,'hardcopy');
assertFolder(count($distribution['by_folder']['location:201'])===2,
  'Location A must contain multiple authorized document files');
assertFolder(count($distribution['by_folder']['location:202'])===1,
  'Location B must contain its own documents as a sibling');
assertFolder(count($distribution['by_folder']['location:203'])===1,
  'Locations without Assets must appear under their Specific');
assertFolder(count($distribution['by_folder']['area:2'])===1,
  'Documents without deeper Places must remain in the deepest active folder');
assertFolder(count($distribution['by_folder']['unfiled'])===1,
  'Unfiled documents must remain accessible');
assertFolder($distribution['counts']['asset:100']===3 &&
  $distribution['counts']['specific:10']===3 &&
  $distribution['counts']['area:1']===4 &&
  $distribution['counts']['area:2']===1,
  'Branch document counts must aggregate over nested location nodes');
$softDocs=Document_folder_tree::distribute([
 ['record_path'=>['category_id'=>8],'id'=>4],
 ['record_path'=>['category_id'=>9],'id'=>5],
 ['record_path'=>['category_id'=>9],'id'=>6]
],$softcopy,'softcopy');
assertFolder(count($softDocs['by_folder']['category:9'])===2 &&
  $softDocs['counts']['category:7']===3,
  'Nested Softcopy category tree must show several documents in its subfolders');

$treeScript=file_get_contents($root.'/public/assets/js/document-tree.js');
$viewCss=file_get_contents($root.'/public/assets/css/unified-ux.css');
$footerHtml=file_get_contents($root.'/application/view/layout/footer.php');
assertFolder(strpos($folderView,'data-folder-node')!==FALSE &&
  strpos($folderView,'<summary')!==FALSE &&
  strpos($folderView,'$renderNode($child,$depth+1)')!==FALSE,
  'Folder mode must recursively render expandable nested folder rows');
assertFolder(strpos($folderView,'$renderDocument($dt_row)')!==FALSE &&
  strpos($folderView,'$tree[\'roots\']')!==FALSE,
  'Nested folder rows must include document files from multiple root branches');
assertFolder(strpos($treeScript,"details[data-folder-node]")!==FALSE &&
  strpos($footerHtml,'document-tree.js')!==FALSE,
  'Expand all / Collapse all must be available progressively');
assertFolder(strpos($viewCss,'.doc-tree-children')!==FALSE &&
  strpos($viewCss,'.document-layout-choice.is-active')!==FALSE,
  'Folder hierarchy and enhanced active view toggle require accessible CSS');
assertFolder(strpos($documentView,'document-layout-choice-copy')!==FALSE,
  'Both layout choices must have a prominent label and subtitle');
assertFolder(strpos($folderView,'HIERARCHICAL DOCUMENT LIBRARY')===FALSE &&
    strpos($folderView,'Folder explorer')===FALSE,
    'Folder mode must not repeat the removed decorative explorer headings');
assertFolder(strpos($folderView,'doc-folder-explorer-header')===FALSE,
    'Folder mode should start directly with breadcrumbs and controls');
$docTreeHelper=file_get_contents($root.'/application/services/presentation/document_folder_tree.php');
assertFolder(strpos($docTreeHelper,"'asset'=>'asset_id'")!==FALSE,
    'Document entries must resolve Asset folders when no Location is assigned');
assertFolder(strpos($folderModel,"if (\$kind==='asset')")!==FALSE &&
    strpos($folderModel,"\$parent='asset'")!==FALSE,
    'Assets and their Locations must appear as navigable folders');
assertFolder(strpos($folderView,'Document Repository')===FALSE,
    'Folder mode must start directly at the real folders');
// Render the real folder view: document counts must never open every branch
// at the repository root. A deep link should open only its ancestor chain.
define('APPPATH', $root.'/application/');
function site_url($path='') { return '/'.$path; }
function html_escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
class FolderViewHarness {
    public $load;
    public function __construct() { $this->load=$this; }
    public function view($name,$data=[]) { echo '<button>View</button>'; }
    public function render(array $data): string {
        extract($data);
        ob_start();
        include APPPATH.'view/reusable_components/document_folder_list.php';
        return ob_get_clean();
    }
}
$viewRows=[];
foreach ($rows as $row) $viewRows[]=$row+[
    'cells'=>['title'=>'Test Document','status'=>'active'], 'display'=>[], 'buttons'=>[]
];
$viewData=[
    'dt_folder_browser'=>['tree'=>$multi,'folder'=>'','breadcrumbs'=>[]],
    'dt_folder_domain'=>'hardcopy','dt_rows'=>$viewRows,
    'dt_columns'=>['title'=>'Document','status'=>'Status'],
    'dt_path'=>'documents/hardcopy','dt_extra_params'=>[],
    'dt_q'=>'','dt_filter_values'=>[],'dt_limit'=>25,'dt_sort'=>'title','dt_dir'=>'asc'
];
$harness=new FolderViewHarness();
$opened=static function(string $html): array {
    preg_match_all('/data-folder-key="([^"]+)">\s*<details data-folder-node open>/', $html, $matches);
    return $matches[1];
};
assertFolder($opened($harness->render($viewData))===[],
    'Repository root must show the first folder level without opening all populated descendants');
$viewData['dt_folder_browser']['folder']='location:201';
assertFolder($opened($harness->render($viewData))===['area:1','specific:10','asset:100','location:201'],
    'A selected Location must open only its Area > Specific > Asset > Location chain');
$viewData['dt_folder_browser']['folder']='all';
assertFolder($opened($harness->render($viewData))===[],
    'All Documents must preserve the hierarchy instead of auto-expanding populated branches');
echo "System Documents exclusive folder/list mode and hierarchy contracts passed.\n";
