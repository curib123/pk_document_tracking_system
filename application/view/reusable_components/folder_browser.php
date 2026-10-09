<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$browser = $folder_browser;
$base = $folder_base;
$preserve = $folder_params ?? [];
$url = function($folder) use($base,$preserve) {
    $query=$preserve;
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
 </div>
 <nav class="folder-breadcrumbs" aria-label="Current folder">
 <?php foreach ($browser['breadcrumbs'] as $i=>$crumb): ?>
   <?php if ($i): ?><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><?php endif; ?>
   <?php if ($i===count($browser['breadcrumbs'])-1): ?>
     <span aria-current="page"><?= html_escape($crumb['name']) ?></span>
   <?php else: ?>
     <a href="<?= html_escape($url($crumb['folder'])) ?>"><?= html_escape($crumb['name']) ?></a>
   <?php endif; ?>
 <?php endforeach; ?>
 </nav>
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
