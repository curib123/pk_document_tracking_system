<?php
define('BASEPATH',__DIR__);
require dirname(__DIR__).'/application/services/presentation/folder_tree.php';
$nodes=[
 ['key'=>'category:1','parent'=>'','name'=>'Parent','kind'=>'category'],
 ['key'=>'category:2','parent'=>'category:1','name'=>'Child','kind'=>'category'],
 ['key'=>'category:3','parent'=>'category:4','name'=>'Cycle 1','kind'=>'category'],
 ['key'=>'category:4','parent'=>'category:3','name'=>'Cycle 2','kind'=>'category'],
 ['key'=>'category:5','parent'=>'category:88','name'=>'Orphan','kind'=>'category'],
 ['key'=>'location:9','parent'=>'','name'=>'Standalone location','kind'=>'location']
];
$tree=Folder_tree::build($nodes);
if (count($tree['roots'])!==2 || count($tree['nodes'])!==3 || $tree['unavailable']!==3) throw new RuntimeException('Folder cycle/orphan handling failed');
if ($tree['nodes']['category:1']['children']!==['category:2']) throw new RuntimeException('Child hierarchy lost');
if ($tree['nodes']['location:9']['parent']!=='') throw new RuntimeException('Standalone location missing');
$deep=[];for($i=1;$i<=35;$i++) $deep[]=['key'=>'category:'.$i,'parent'=>$i===1?'':'category:'.($i-1),'name'=>'N'.$i,'kind'=>'category'];
$tree=Folder_tree::build($deep);
if (count($tree['nodes'])!==32 || $tree['unavailable']!==3) throw new RuntimeException('Depth guard failed');
echo "Folder hierarchy passed: nested, root, standalone, cycle, orphan and depth cases.\n";
