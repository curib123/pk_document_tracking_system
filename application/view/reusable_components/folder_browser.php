<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$browser = $folder_browser;
$base = $folder_base;
$preserve = $folder_params ?? [];
$layout=($preserve['layout']??'table')==='grid'?'grid':'table';
$url = function($folder) use($base,$preserve) {
    $query=$preserve; unset($query['page']);
    if ($folder==='') unset($query['folder']);
    else $query['folder']=$folder;
    return site_url($base).($query?'?'.http_build_query($query):'');
};
$icons=['area'=>'fa-layer-group','specific'=>'fa-crosshairs',
  'asset'=>'fa-barcode','location'=>'fa-location-dot',
  'category'=>'fa-folder','root'=>'fa-folder-open'];
?>
<section class="folder-explorer" aria-label="Document folder navigation">
 <div class="folder-explorer-header">
  <div>
   <span class="eyebrow">Folder Explorer</span>
   <h2 class="fs-6 fw-bold mb-1"><?= html_escape($browser['label']) ?></h2>
   <p class="small text-secondary mb-0">
     <?= $domain==='hardcopy'
         ? 'Area → Specific → Asset → Location'
         : 'Parent Category → Subcategory' ?>
   </p>
  </div>
  <?php if ($browser['folder']!==''): ?>
  <a class="btn btn-light btn-sm" href="<?= html_escape($url('')) ?>">
    <i class="fa-solid fa-house me-1" aria-hidden="true"></i> All Documents
  </a>
  <?php endif; ?>
  <div class="btn-group" role="group" aria-label="Document layout">
   <?php foreach (['grid'=>'Folder & Cards','table'=>'Data Table'] as $mode=>$label):
     $query=$preserve; $query['layout']=$mode; $query['folder']=$browser['folder'];
     $query['page']=$folder_page??1; ?>
    <a class="btn btn-sm <?= $layout===$mode?'btn-primary':'btn-light' ?>"
       href="<?= html_escape(site_url($base).'?'.http_build_query($query)) ?>"
       <?= $layout===$mode?'aria-current="true"':'' ?>><?= $label ?></a>
   <?php endforeach; ?>
  </div>
 </div>
 <nav class="folder-breadcrumbs aria-label="Current folder">
 <?php foreach ($browser['breadcrumbs'] as $i=>$crumb): ?>
   <?php if ($i): ?><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><?php endif; ?>
   <?php if ($i===count($browser['breadcrumbs'])-1): ?>
     <span aria-current="page"><?= html_escape($crumb['name']) ?></span>
   <?php else: ?>
     <a href="<?= html_escape($url($crumb['folder'])) ?>"><?= html_escape($crumb['name']) ?></a>
   <?php endif; ?>
 <?php endforeach; ?>
 </nav>
 <?php if (!empty($browser['tree']['nodes'])): ?>
 <details class="folder-tree-shell mb-3" <?= $layout==='grid'?'open':'' ?>>
  <summary class="fw-semibold">Folder hierarchy</summary>
  <nav class="folder-tree" aria-label="Nested folder hierarchy">
   <?php
   $treeNodes=$browser['tree']['nodes'];
   $activePath=array_column($browser['breadcrumbs'],'folder');
   $renderNodes=function($keys) use(&$renderNodes,$treeNodes,$activePath,$browser,$url) {
       echo '<ul>';
       foreach ($keys as $key) {
           $node=$treeNodes[$key]; $current=$key===$browser['folder'];
           echo '<li>';
           if ($node['children']) echo '<details'.(in_array($key,$activePath,TRUE)?' open':'').'><summary>';
           echo '<a href="'.html_escape($url($key)).'"'.($current?' aria-current="page"':'').'>'.
               html_escape($node['name']).'</a>';
           if ($node['children']) {
               echo '</summary>'; $renderNodes($node['children']); echo '</details>';
           }
           echo '</li>';
       }
       echo '</ul>';
   };
   $renderNodes($browser['tree']['roots']);
   ?>
  </nav>
  <?php if (!empty($browser['tree']['unavailable'])): ?>
   <p class="small text-secondary mb-0">Some branches are unavailable because a parent is inactive or their hierarchy needs correction in Places.</p>
  <?php endif; ?>
 </details>
 <?php endif; ?>
 <?php if ($browser['children']): ?>
 <div class="folder-tiles">
  <?php foreach ($browser['children'] as $entry): ?>
  <a class="folder-tile" href="<?= html_escape($url($entry['folder'])) ?>">
   <span class="folder-tile-icon"><i class="fa-solid <?= html_escape($icons[$entry['kind']]??'fa-folder') ?>" aria-hidden="true"></i></span>
   <span class="folder-tile-text">
    <strong><?= html_escape($entry['name']) ?></strong>
    <small><?= html_escape($entry['meta']) ?></small>
   </span>
   <i class="fa-solid fa-chevron-right folder-tile-arrow" aria-hidden="true"></i>
  </a>
  <?php endforeach; ?>
 </div>
 <?php elseif ($browser['level']!=='root'): ?>
 <p class="small text-secondary mb-0">This folder has no subfolders. Records in this folder appear below.</p>
 <?php endif; ?>
</section>
