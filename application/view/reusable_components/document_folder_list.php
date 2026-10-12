<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Vertical directory presentation, using the SAME permission-scoped dt_rows,
 * record actions, filters and pagination as the System Documents table.
 * Folder navigation is server-rendered; no extra API endpoints or AJAX.
 */
$browser=$dt_folder_browser;
$isRoot=$browser['level']==='root';
$folderUrl=static function($folder,$sort=NULL,$dir=NULL) use(
    $dt_path,$dt_extra_params,$dt_q,$dt_filter_values,$dt_limit,$dt_sort,$dt_dir
) {
    $params=array_merge($dt_extra_params,['q'=>$dt_q],$dt_filter_values,[
        'page'=>1,'limit'=>$dt_limit,'sort'=>$sort??$dt_sort,'dir'=>$dir??$dt_dir,
        'folder'=>$folder,'layout'=>'folder'
    ]);
    return site_url($dt_path).'?'.http_build_query($params);
};
$kindLabels=['area'=>'Area','specific'=>'Specific','asset'=>'Asset',
    'location'=>'Location','category'=>'Category'];
$sortRecent=($dt_sort==='updated_at' || $dt_sort==='updated') && strtolower($dt_dir)==='desc';
$sortAZ=$dt_sort==='title' && strtolower($dt_dir)==='asc';
$sortZA=$dt_sort==='title' && strtolower($dt_dir)==='desc';
?>
<div class="doc-folder-explorer" data-folder-domain="<?= html_escape($dt_folder_domain) ?>" data-folder-current="<?= html_escape($browser['folder']) ?>">
    <div class="doc-folder-explorer-header">
        <div>
            <div class="doc-folder-explorer-eyebrow"><i class="fa-solid fa-folder-tree" aria-hidden="true"></i> DOCUMENT LIBRARY</div>
            <h2 class="doc-folder-explorer-title"><?= html_escape($isRoot?'Browse folders':$browser['label']) ?></h2>
            <p class="doc-folder-explorer-subtitle">
                <?= $dt_folder_domain==='hardcopy'
                    ? 'Area → Specific → Asset → Location → Documents'
                    : 'Category → Subcategory → Documents' ?>
            </p>
        </div>
        <span class="doc-folder-explorer-count"><?= count($browser['children']) ?> folders<?= $isRoot?'':' · '.(int)$dt_total.' documents' ?></span>
    </div>

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

    <div class="doc-folder-rows" role="list" aria-label="Folder contents">
        <?php if ($isRoot): ?>
            <a class="doc-folder-entry doc-folder-directory" role="listitem"
               href="<?= html_escape($folderUrl('all')) ?>">
                <span class="doc-folder-entry-icon doc-folder-entry-icon-all"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                <span class="doc-folder-entry-content">
                    <strong>All Documents</strong>
                    <small>View every document accessible to you, including records without a folder</small>
                </span>
                <span class="doc-folder-entry-type"><?= (int)$dt_total ?> records</span>
                <i class="fa-solid fa-chevron-right doc-folder-arrow" aria-hidden="true"></i>
            </a>
        <?php endif; ?>

        <?php foreach ($browser['children'] as $child): ?>
            <a class="doc-folder-entry doc-folder-directory" role="listitem"
               href="<?= html_escape($folderUrl($child['folder'])) ?>">
                <span class="doc-folder-entry-icon"><i class="fa-solid fa-folder" aria-hidden="true"></i></span>
                <span class="doc-folder-entry-content">
                    <strong><?= html_escape($child['name']) ?></strong>
                    <small><?= html_escape($child['meta']?:($kindLabels[$child['kind']]??'Folder')) ?></small>
                </span>
                <span class="doc-folder-entry-type"><?= html_escape($kindLabels[$child['kind']]??'Folder') ?></span>
                <i class="fa-solid fa-chevron-right doc-folder-arrow" aria-hidden="true"></i>
            </a>
        <?php endforeach; ?>

        <?php if (!$isRoot): ?>
            <?php if ($dt_rows): ?>
                <div class="doc-folder-files-heading" role="presentation">
                    <strong><i class="fa-regular fa-file-lines me-2" aria-hidden="true"></i>Documents</strong>
                    <div class="doc-folder-sort" aria-label="Sort document list">
                        <a class="<?= $sortRecent?'is-active':'' ?>" href="<?= html_escape($folderUrl($browser['folder'],'updated_at','desc')) ?>" <?= $sortRecent?'aria-current="true"':'' ?>>Recent</a>
                        <a class="<?= $sortAZ?'is-active':'' ?>" href="<?= html_escape($folderUrl($browser['folder'],'title','asc')) ?>" <?= $sortAZ?'aria-current="true"':'' ?>>A–Z</a>
                        <a class="<?= $sortZA?'is-active':'' ?>" href="<?= html_escape($folderUrl($browser['folder'],'title','desc')) ?>" <?= $sortZA?'aria-current="true"':'' ?>>Z–A</a>
                    </div>
                </div>
            <?php endif; ?>
            <?php foreach ($dt_rows as $dt_row): ?>
                <?php
                $cells=$dt_row['cells'];
                $documentTitle=(string)($cells['title']??'Untitled document');
                $reference=(string)($cells[$dt_folder_domain==='hardcopy'?'sequence_number':'document_number']??'');
                $status=(string)($cells['status']??'');
                ?>
                <article class="doc-folder-entry doc-folder-file" role="listitem"
                         data-record-id="<?= (int)$dt_row['id'] ?>">
                    <span class="doc-folder-entry-icon doc-folder-file-icon">
                        <i class="fa-regular <?= $dt_folder_domain==='hardcopy'?'fa-folder-closed':'fa-file-lines' ?>" aria-hidden="true"></i>
                    </span>
                    <div class="doc-folder-entry-content">
                        <button class="doc-folder-file-open js-view" type="button"
                                data-bs-toggle="modal" data-bs-target="#viewModal"
                                data-title="View Document Details"
                                data-display="<?= html_escape(json_encode($dt_row['display'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)) ?>"
                                aria-label="View <?= html_escape($documentTitle) ?> details">
                            <?= html_escape($documentTitle) ?>
                        </button>
                        <div class="doc-folder-file-meta">
                            <?php foreach ($dt_columns as $key=>$label): if ($key==='title' || $key==='status') continue; ?>
                                <span><span class="doc-folder-meta-label"><?= html_escape($label) ?>:</span>
                                <?= html_escape((string)($cells[$key]?:'—')) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <span class="badge-status status-<?= html_escape(preg_replace('/[^a-z0-9-]+/','-',strtolower($status))) ?> doc-folder-file-status">
                        <?= html_escape(ucwords(str_replace('_',' ',$status?:'Unknown'))) ?>
                    </span>
                    <div class="table-actions doc-folder-file-actions">
                        <?php $this->load->view('reusable_components/record_actions',['dt_row'=>$dt_row]); ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!$browser['children'] && !$isRoot && !$dt_rows): ?>
            <div class="doc-folder-empty" role="status">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <strong>No documents in this folder</strong>
                <p>Try another folder or change the search and filters.</p>
                <a class="btn btn-outline-secondary btn-sm" href="<?= html_escape($folderUrl('all')) ?>">View all documents</a>
            </div>
        <?php elseif ($isRoot && !$browser['children']): ?>
            <div class="doc-folder-empty" role="status">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <strong>No folders configured yet</strong>
                <p>Use All Documents to access existing records.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
