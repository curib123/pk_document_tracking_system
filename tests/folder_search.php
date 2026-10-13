<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
require_once APPPATH.'services/presentation/folder_tree.php';
require_once APPPATH.'services/presentation/hardcopy_folder_tree.php';
require_once APPPATH.'services/presentation/folder_visibility.php';
function search_check(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
$service=APPPATH.'services/presentation/folder_search.php';
search_check(is_file($service),'Folder search must resolve matching hierarchy paths');require_once $service;
$places=Folder_tree::build([
 ['key'=>'area:1','parent'=>'','name'=>'Office','kind'=>'area'],['key'=>'area:2','parent'=>'','name'=>'Production','kind'=>'area'],
 ['key'=>'specific:10','parent'=>'area:1','name'=>'Cabinet','kind'=>'specific'],['key'=>'specific:20','parent'=>'area:2','name'=>'Shelf','kind'=>'specific'],
 ['key'=>'location:9','parent'=>'','name'=>'Shared drawer','meta'=>'D-09','kind'=>'location']
]);
$paths=[['area_id'=>1,'specific_id'=>10,'asset_id'=>NULL,'location_id'=>9],['area_id'=>2,'specific_id'=>20,'asset_id'=>NULL,'location_id'=>9]];
$tree=Hardcopy_folder_tree::project($places,$paths);$browser=['tree'=>$tree,'folder'=>''];
$match=Folder_search::for_browser($browser,'OFFICE','hardcopy');
search_check($match['paths']===[$paths[0]],'A matching Area must include only its real document filing paths');
search_check(isset($match['visible']['location:9:specific:10'])&&!isset($match['visible']['area:2']),'Folder-name matches must reveal descendants without unrelated branches');
$match=Folder_search::for_browser($browser,'d-09','hardcopy');search_check(count($match['paths'])===2,'Location codes must match each authorized shared Location context');
$browser['folder']='specific:10';$match=Folder_search::for_browser($browser,'Office','hardcopy');search_check($match['paths']===[$paths[0]],'Search in a selected folder must include ancestor-name matches within that scope');
$match=Folder_search::for_browser($browser,'Production','hardcopy');search_check($match['paths']===[]&&$match['visible']===[],'Searching a selected branch must not escape to other Areas');
$browser=['tree'=>Folder_visibility::restrict($tree,[$paths[0]],'hardcopy'),'folder'=>''];$match=Folder_search::for_browser($browser,'Production','hardcopy');search_check($match['visible']===[]&&$match['paths']===[],'Private folder names cannot match or reveal private records');
$soft=Folder_tree::build([['key'=>'category:1','parent'=>'','name'=>'Policies','kind'=>'category'],['key'=>'category:2','parent'=>'category:1','name'=>'Safety','kind'=>'category'],['key'=>'category:3','parent'=>'','name'=>'Other','kind'=>'category']]);
$match=Folder_search::for_browser(['tree'=>$soft,'folder'=>'category:1'],'Policies','softcopy');sort($match['categories']);search_check($match['categories']===[1,2]&&$match['scope_categories']===[1,2],'Category-name search must include nested files inside the selected category');
echo "Folder-name search, contextual paths, nested categories and privacy checks passed.\n";
