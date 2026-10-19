<?php
define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/services/presentation/folder_tree.php';
$tree = Folder_tree::build([
    ['key'=>'category:1','parent'=>'','name'=>'Parent'],
    ['key'=>'category:2','parent'=>'category:1','name'=>'Child'],
    ['key'=>'category:3','parent'=>'category:2','name'=>'Grandchild'],
    ['key'=>'category:4','parent'=>'','name'=>'Sibling'],
]);
if (!method_exists('Folder_tree', 'branch')) throw new RuntimeException('Category branch selection is missing.');
if (Folder_tree::branch($tree, 'category:1') !== ['category:1','category:2','category:3'])
    throw new RuntimeException('A parent must include every descendant, never siblings.');
if (Folder_tree::branch($tree, 'category:2') !== ['category:2','category:3'])
    throw new RuntimeException('A subcategory must include only its own branch.');
try { Folder_tree::branch($tree, 'category:99'); throw new RuntimeException('Missing category accepted.'); }
catch (DomainException $expected) {}
echo "Category parent, subcategory, grandchild, sibling isolation and invalid selection passed.\n";
