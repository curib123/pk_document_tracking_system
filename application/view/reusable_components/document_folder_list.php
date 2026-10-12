<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Nested folder explorer — expands Area → Specific → Location → files
 * (or Category → Subcategory → files for Softcopy) IN THE SAME VIEW.
 * No additional queries or APIs: reuse the authorized paginated dt_rows.
 */
require_once APPPATH.'services/presentation/document_folder_tree.php';
$browser=$dt_folder_browser;
$tree=$browser['tree'];
$nodes=$tree['nodes']??[];
$distribution=Document_folder_tree::distribute($dt_rows,$tree,$dt_folder_domain);
$byFolder=$distribution['by_folder'];
$counts=$distribution['counts'];
$folderUrl=static function($folder,$sort=NULL,$dir=NULL) use(
    $dt_path,$dt_extra_params,$dt_q,$dt_filter_values,$dt_limit,$dt_sort,$dt_dir
) {
    $params=array_merge($dt_extra_params,['q'=>$dt_q],$dt_filter_values,[
        'page'=>1,'limit'=>$dt_limit,'sort'=>$sort??$dt_sort,'dir'=>$dir??$dt_dir,
        'folder'=>$folder,'layout'=>'folder'
    ]);
    return site_url($dt_path).'?'.http_build_query($params);
};
$kinds=['area'=>'Area','specific'=>'Specific','location'=>'Location',
    'category'=>'Category'];
$currentFolder=$browser['folder'];
$selectedChain=[];
$at=$currentFolder;
while ($at!=='' && $at!=='all' && isset($nodes[$at]) && count($selectedChain)<32) {
    $selectedChain[$at]=TRUE;
    $at=(string)$nodes[$at]['parent'];
}
$sortRecent=($dt_sort==='updated_at' || $dt_sort==='updated') && strtolower($dt_dir)==='desc';
$sortAZ=$dt_sort==='title' && strtolower($dt_dir)==='asc';
$sortZA=$dt_sort==='title' && strtolower($dt_dir)==='desc';

$renderDocument=function(array $dt_row) use($dt_columns,$dt_folder_domain) {
    $cells=$dt_row['cells'];
    $title=(string)($cells['title']??'Untitled document');
    $fileName=(string)($dt_row['file_name']??'');
    $displayName=$fileName!==''?$fileName:$title;
    $status=(string)($cells['status']??'');
    $extension=strtolower((string)pathinfo($displayName,PATHINFO_EXTENSION));
    $fileIcon=[
        'pdf'=>'fa-file-pdf','xlsx'=>'fa-file-excel','xls'=>'fa-file-excel',
        'doc'=>'fa-file-word','docx'=>'fa-file-word','jpg'=>'fa-file-image',
        'jpeg'=>'fa-file-image','png'=>'fa-file-image','txt'=>'fa-file-lines'
    ][$extension]??'fa-file-lines';
    ?>
    <li class="doc-tree-file" data-record-id="<?= (int)$dt_row['id'] ?>">
        <span class="doc-tree-file-icon" aria-hidden="true"><i class="fa-regular <?= html_escape($fileIcon) ?>"></i></span>
        <div class="doc-tree-file-content">
            <button class="doc-tree-file-name js-view" type="button"
                    data-bs-toggle="modal" data-bs-target="#viewModal"
                    data-title="View Document Details"
                    data-display="<?= html_escape(json_encode($dt_row['display'],
                        JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)) ?>"
                    aria-label="View <?= html_escape($title) ?> details"><?= html_escape($displayName) ?></button>
            <?php if ($fileName!=='' && $fileName!==$title): ?>
                <span class="doc-tree-document-title"><?= html_escape($title) ?></span>
            <?php endif; ?>
            <div class="doc-tree-file-meta">
                <?php foreach ($dt_columns as $key=>$label): if ($key==='title' || $key==='status') continue; ?>
                    <span><span class="doc-folder-meta-label"><?= html_escape($label) ?>:</span>
                        <?= html_escape((string)($cells[$key]??'')!==''?(string)$cells[$key]:'—') ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <span class="badge-status status-<?= html_escape(preg_replace('/[^a-z0-9-]+/','-',strtolower($status))) ?> doc-tree-file-status">
            <?= html_escape(ucwords(str_replace('_',' ',$status?:'Unknown'))) ?>
        </span>
        <div class="table-actions doc-tree-actions">
            <?php $this->load->view('reusable_components/record_actions',['dt_row'=>$dt_row]); ?>
        </div>
    </li>
    <?php
};

$renderNode=NULL;
$renderNode=function($key,$depth=0) use(
    &$renderNode,$nodes,$counts,$byFolder,$renderDocument,$currentFolder,
    $selectedChain,$folderUrl,$kinds,$dt_folder_domain
) {
    if ($depth>=32 || !isset($nodes[$key])) return;
    $node=$nodes[$key];
    $items=$byFolder[$key]??[];
    $children=$node['children']??[];
    $count=(int)($counts[$key]??0);
    $isCurrent=$key===$currentFolder;
    $open=$count>0 || isset($selectedChain[$key]);
    $label=$kinds[$node['kind']]??'Folder';
    ?>
    <li class="doc-tree-node" data-folder-key="<?= html_escape($key) ?>">
        <details data-folder-node <?= $open?'open':'' ?>>
            <summary class="doc-tree-folder-row <?= $isCurrent?'is-current':'' ?>">
                <span class="doc-tree-caret" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="doc-tree-folder-icon" aria-hidden="true"><i class="fa-solid fa-folder"></i></span>
                <span class="doc-tree-folder-text">
                    <strong><?= html_escape($node['name']) ?></strong>
                    <small><?= html_escape($label) ?></small>
                </span>
                <?php if ($count): ?>
                    <span class="doc-tree-node-count" aria-label="<?= $count ?> documents on this page"><?= $count ?> on page</span>
                <?php endif; ?>
            </summary>
            <ul class="doc-tree-children">
                <?php foreach ($children as $child): $renderNode($child,$depth+1); endforeach; ?>
                <?php foreach ($items as $dt_row): $renderDocument($dt_row); endforeach; ?>
                <?php if ($isCurrent): ?>
                    <li class="doc-tree-view-folder">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        <span>Browsing this <?= html_escape(strtolower($label)) ?></span>
                    </li>
                <?php else: ?>
                    <li class="doc-tree-view-folder">
                        <a href="<?= html_escape($folderUrl($key)) ?>" title="Filter the register to this folder and browse all its documents">
                            <i class="fa-solid fa-filter" aria-hidden="true"></i>
                            Browse this <?= html_escape(strtolower($label)) ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </details>
    </li>
    <?php
};
?>
<div class="doc-folder-explorer doc-folder-tree-mode"
     data-folder-domain="<?= html_escape($dt_folder_domain) ?>"
     data-folder-current="<?= html_escape($currentFolder) ?>">
    <div class="doc-tree-toolbar">
        <nav class="doc-folder-breadcrumb" aria-label="Current document folder">
            <?php foreach ($browser['breadcrumbs'] as $index=>$crumb): ?>
                <?php if ($index>0): ?><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><?php endif; ?>
                <?php if ($index===count($browser['breadcrumbs'])-1): ?>
                    <span aria-current="page"><?= html_escape($crumb['name']) ?></span>
                <?php else: ?>
                    <a href="<?= html_escape($folderUrl($crumb['folder'])) ?>"><?= html_escape($crumb['name']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="doc-tree-toolbar-actions" role="group" aria-label="Folder tree controls">
            <button type="button" class="doc-tree-control" data-tree-action="expand"><i class="fa-solid fa-angles-down" aria-hidden="true"></i> Expand all</button>
            <button type="button" class="doc-tree-control" data-tree-action="collapse"><i class="fa-solid fa-angles-up" aria-hidden="true"></i> Collapse all</button>
        </div>
    </div>
    <div class="doc-tree-filter-line">
        <a href="<?= html_escape($folderUrl('all')) ?>"
           class="doc-tree-all-link <?= $currentFolder==='all'?'is-active':'' ?>"
           <?= $currentFolder==='all'?'aria-current="page"':'' ?>>
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i> All documents
        </a>
        <span>Open a folder arrow to explore multiple branches. Only authorized documents on this page are listed.</span>
        <div class="doc-folder-sort" aria-label="Document ordering">
            <a class="<?= $sortRecent?'is-active':'' ?>"
               href="<?= html_escape($folderUrl($currentFolder,'updated_at','desc')) ?>">Recent</a>
            <a class="<?= $sortAZ?'is-active':'' ?>"
               href="<?= html_escape($folderUrl($currentFolder,'title','asc')) ?>">A–Z</a>
            <a class="<?= $sortZA?'is-active':'' ?>"
               href="<?= html_escape($folderUrl($currentFolder,'title','desc')) ?>">Z–A</a>
        </div>
    </div>
    <div class="doc-tree-scroll">
        <ul class="doc-tree-root" aria-label="<?= html_escape($dt_folder_domain==='hardcopy'?'Hardcopy location tree':'Softcopy category tree') ?>">
            <?php foreach ($tree['roots']??[] as $key): $renderNode($key); endforeach; ?>
            <?php if (!empty($byFolder['unfiled'])): ?>
                <li class="doc-tree-node doc-tree-unfiled">
                    <details data-folder-node open>
                        <summary class="doc-tree-folder-row">
                            <span class="doc-tree-caret" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
                            <span class="doc-tree-folder-icon" aria-hidden="true"><i class="fa-solid fa-folder-open"></i></span>
                            <span class="doc-tree-folder-text"><strong>Unfiled documents</strong><small>No active folder assignment</small></span>
                            <span class="doc-tree-node-count"><?= count($byFolder['unfiled']) ?> on page</span>
                        </summary>
                        <ul class="doc-tree-children">
                            <?php foreach ($byFolder['unfiled'] as $row): $renderDocument($row); endforeach; ?>
                        </ul>
                    </details>
                </li>
            <?php endif; ?>
        </ul>
        <?php if (empty($tree['roots']) && empty($byFolder['unfiled'])): ?>
            <div class="doc-folder-empty" role="status">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <strong>No folders or documents available</strong>
                <p>Try the All Documents link or change the search filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
