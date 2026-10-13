<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);
define('APPPATH',dirname(__DIR__).'/application/');
require_once APPPATH.'services/presentation/folder_tree.php';
require_once APPPATH.'services/presentation/document_folder_tree.php';
require_once APPPATH.'services/presentation/folder_visibility.php';
class CI_Model {public $db;}
require_once APPPATH.'models/Folder_model.php';
function contextual_check(bool $condition,string $message):void {
    if(!$condition)throw new RuntimeException($message);
}
// Imported location labels are reusable; the document carries its actual filing path.
$places=Folder_tree::build([
 ['key'=>'area:1','parent'=>'','name'=>'Office','kind'=>'area'],
 ['key'=>'area:2','parent'=>'','name'=>'Production','kind'=>'area'],
 ['key'=>'specific:10','parent'=>'area:1','name'=>'Cabinet','kind'=>'specific'],
 ['key'=>'specific:20','parent'=>'area:2','name'=>'Shelf','kind'=>'specific'],
 ['key'=>'asset:100','parent'=>'specific:10','name'=>'Asset A','kind'=>'asset'],
 ['key'=>'asset:200','parent'=>'specific:20','name'=>'Asset B','kind'=>'asset'],
 ['key'=>'location:9','parent'=>'','name'=>'Drawer 1','meta'=>'D1','kind'=>'location'],
 ['key'=>'location:8','parent'=>'asset:100','name'=>'Drawer 2','meta'=>'D2','kind'=>'location']
]);
$paths=[
 ['area_id'=>1,'specific_id'=>10,'asset_id'=>100,'location_id'=>9],
 ['area_id'=>2,'specific_id'=>20,'asset_id'=>200,'location_id'=>9],
 ['area_id'=>1,'specific_id'=>10,'asset_id'=>NULL,'location_id'=>9],
 ['area_id'=>NULL,'specific_id'=>NULL,'asset_id'=>NULL,'location_id'=>8],
 ['area_id'=>1,'specific_id'=>NULL,'asset_id'=>NULL,'location_id'=>NULL],
 ['area_id'=>NULL,'specific_id'=>NULL,'asset_id'=>NULL,'location_id'=>9]
];
$service=APPPATH.'services/presentation/hardcopy_folder_tree.php';
contextual_check(is_file($service),'Hardcopy layout must project imported Locations into document filing paths');
require_once $service;
$tree=Hardcopy_folder_tree::project($places,$paths);
contextual_check($tree['roots']===['area:1','area:2'],'Only Areas may appear at the repository root');
contextual_check($tree['nodes']['asset:100']['children']===['location:9:asset:100','location:8:asset:100'],
 'A shared unlinked Location must appear beneath the document Asset, beside linked Locations');
contextual_check($tree['nodes']['asset:200']['children']===['location:9:asset:200'],
 'The same Location must also appear below its other document Asset');
contextual_check(in_array('location:9:specific:10',$tree['nodes']['specific:10']['children'],TRUE),
 'A document without an Asset must keep its Location directly under its Specific');
contextual_check(count($tree['nodes']['area:1']['children'])===1,'Existing child folders must not be duplicated');
$rows=[];foreach($paths as$i=>$path)$rows[]=['id'=>$i+1,'record_path'=>$path];
$distribution=Document_folder_tree::distribute($rows,$tree,'hardcopy');
contextual_check(array_column($distribution['by_folder']['location:9:asset:100'],'id')===[1],
 'Documents must remain in their own branch when a Location ID is shared');
contextual_check(array_column($distribution['by_folder']['location:9:asset:200'],'id')===[2],
 'The other Asset must contain only its own document');
contextual_check(array_column($distribution['by_folder']['location:8:asset:100'],'id')===[4],
 'A linked Location must supply missing parent references without changing the stored document');
contextual_check(array_column($distribution['by_folder']['unfiled'],'id')===[6],
 'A document with no known hierarchy must remain accessible as Unfiled');
contextual_check($distribution['counts']['area:1']===4&&$distribution['counts']['area:2']===1,
 'Branch counts must aggregate actual placements, without duplicate files');
$visible=Folder_visibility::restrict($tree,[$paths[0]],'hardcopy');
contextual_check($visible['roots']===['area:1']&&!isset($visible['nodes']['area:2']),
 'A shared Location must never disclose another user\'s Area');
contextual_check(isset($visible['nodes']['location:9'])&&!isset($visible['nodes']['location:9:asset:200']),
 'Legacy Location links stay authorized without exposing another contextual Location');
$model=new Folder_model();
$browse=$model->browse('hardcopy','location:9:asset:100',$tree);
contextual_check(array_column($browse['breadcrumbs'],'folder')===['','area:1','specific:10','asset:100','location:9:asset:100'],
 'Contextual Location links must retain the complete hierarchy in breadcrumbs');
contextual_check($browse['document_paths']===[$paths[0]],'Browsing a contextual Location must select only that filing path');
$branch=$model->browse('hardcopy','specific:10',$tree);
contextual_check($branch['document_paths']===[$paths[0],$paths[2],$paths[3]],
 'Browsing a parent must include descendants whose parents are derived from linked Locations');
$legacy=$model->browse('hardcopy','location:9',$tree);
contextual_check($legacy['filter']===['location_id'=>9],
 'Existing Location bookmarks must continue selecting the original Location ID');
contextual_check($legacy['selected_folders']===['location:9:asset:100','location:9:asset:200','location:9:specific:10'],
 'Legacy links must open each matching contextual Location');
final class ContextualQuery {
 public $conditions=[];
 public function group_start(){$this->conditions[]=['open'];return $this;}
 public function or_group_start(){$this->conditions[]=['or'];return $this;}
 public function group_end(){$this->conditions[]=['close'];return $this;}
 public function where($key,$value=NULL){$this->conditions[]=[$key,$value];return $this;}
}
$model->db=new ContextualQuery();$model->scope_documents('hardcopy',$browse);
contextual_check(in_array(['d.location_id',9],$model->db->conditions,TRUE)&&
 in_array(['d.asset_id',100],$model->db->conditions,TRUE),
 'Folder filters must bind the Location and its exact contextual path');
$model->db=new ContextualQuery();$empty=$model->browse('hardcopy','asset:200',Hardcopy_folder_tree::project($places,[]));
$model->scope_documents('hardcopy',$empty);
contextual_check(in_array(['1 = 0',NULL],$model->db->conditions,TRUE),'An empty branch cannot fall back to every document');
echo "Contextual hardcopy hierarchy, shared Locations, exact filters, legacy links and folder privacy passed.\n";
