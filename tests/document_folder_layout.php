<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require_once __DIR__.'/../application/services/presentation/folder_tree.php';
require_once __DIR__.'/../application/services/presentation/query_state.php';

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
assertFolder($tree['nodes']['specific:2']['children']===['asset:3'],'Specific must open asset');
assertFolder($tree['nodes']['asset:3']['children']===['location:4'],'Asset must open location');
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
echo "System Documents exclusive folder/list mode and hierarchy contracts passed.\n";
