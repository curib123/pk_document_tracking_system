<?php
$permissions=$permissions??[];
require_once APPPATH.'services/presentation/document_folder_tree.php';
$documentTreeNodes=$folder_browser['tree']['nodes']??[];
$can=isset($permissions['*']) || !empty($permissions[$cfg['domain']]['direct']);
// Keep document registers scannable. All other metadata remains available in
// the shared permission-scoped View Details modal, not duplicated in the table.
$dt_columns=$cfg['domain']==='hardcopy'
    ? ['title'=>'Document','sequence_number'=>'Copy Number','location_name'=>'Location',
       'holder_name'=>'Holder','status'=>'Status']
    : ['title'=>'Document','document_number'=>'Document Number',
       'category_name'=>'Category','status'=>'Status'];
$dt_rows=[];
foreach ($rows as $r) {
 $cells=[];$display=[];
 foreach ($dt_columns as $field=>$label) {
    $val=$r[$field]??'';
    $cells[$field]=$val;$display[$label]=$val;
 }
 // The modal is fed from the complete document record, never from the
 // deliberately reduced set of visible table cells.
 $display=$document_details[$r['id']]??$display;
 $rec=[];
 foreach (array_merge(['id','version'],$cfg['fields']) as $field) $rec[$field]=$r[$field]??'';
 if ($cfg['domain']==='hardcopy') {
     $rec['holder_name']=$user['name'];
     if (!$is_administrator) $rec['holder_id']=$user['id'];
 }
 if ($cfg['domain']==='softcopy') {
     // Table edit is a direct revision, never a generic metadata patch.
     $rec=[
         'type'=>'softcopy_revise','softcopy_id'=>$r['id'],
         'subject'=>'Revision: '.$r['title'],
         'title'=>$r['title'],'document_number'=>$r['document_number'],
         'series_number'=>$r['series_number']??'','category_id'=>$r['category_id']
     ];
 }
 $buttons=[['type'=>'view']];
 $canEdit=$can && !empty($r['can_write']) && $r['status']==='active';
 if ($canEdit) $buttons[]=['type'=>'edit'];
 if ($cfg['domain']==='softcopy') {
     if ($canEdit && (isset($permissions['*']) || !empty($permissions['files']['upload']))) {
         $buttons[]=['type'=>'upload'];
     }
     if (!empty($file_histories[$r['id']]) && !empty($file_access[$r['id']])) {
         $historyEntries=[];
         foreach ($file_histories[$r['id']]??[] as $item) {
             $historyEntries[]=[
                 'name'=>$item['original_name'],
                 'detail'=>$item['first_name'].' '.$item['last_name'].' — '.$item['created_at'].
                     ' · '.(!empty($item['is_current'])?'Current':'Superseded'),
                 'url'=>!empty($item['is_current'])?site_url('files/download/'.$item['id']):''
             ];
         }
         $buttons[]=['type'=>'history','files'=>$historyEntries];
         if (!empty($latest_files[$r['id']]))
             $buttons[]=['type'=>'download','url'=>'files/download/'.$latest_files[$r['id']]['id']];
     }
 }
 if ($canEdit && (isset($permissions['*']) || !empty($permissions['disposal']['direct']))) $buttons[]=['type'=>'action',
     'url'=>'documents/'.$cfg['domain'].'/dispose','label'=>'Dispose',
     'disposal'=>TRUE,
     'description'=>'Choose a disposal reason and confirm this action.',
     'icon'=>'fa-solid fa-trash-can'];
 $path=['area_id'=>$r['area_id']??NULL,'specific_id'=>$r['specific_id']??NULL,
    'asset_id'=>$r['asset_id']??NULL,'location_id'=>$r['location_id']??NULL,
    'category_id'=>$r['category_id']??NULL];
 $dt_rows[]=['id'=>$r['id'],'cells'=>$cells,'record'=>$rec,'display'=>$display,
    'buttons'=>$buttons,'record_path'=>$path,
    'folder_key'=>Document_folder_tree::folder_key($path,$documentTreeNodes,$cfg['domain']),
    'file_name'=>$cfg['domain']==='softcopy' && !empty($latest_files[$r['id']])
        ? (string)$latest_files[$r['id']]['original_name'] : ''];
}
$dt_path='documents/'.$cfg['domain'];
$dt_extra_params=['folder'=>$folder_value,'layout'=>$table['layout']];$dt_q=$table['q'];$dt_filter=$table['status'];
$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort=$table['sort'];$dt_dir=strtolower($table['dir']);
$dt_sortable=$cfg['domain']==='hardcopy'
    ? ['title','sequence_number','status'] : ['title','document_number','status'];
$dt_date_filters=TRUE;
$dt_filters=['status'=>[''=>'All Statuses','active'=>'Active','disposed'=>'Disposed','archived'=>'Archived','cancelled'=>'Cancelled']];
$dt_filters['owner']=[''=>'All Owners']+array_column($owner_options,'name','id');
$dt_filter_values=['status'=>$dt_filter,'owner'=>$table['owner'],'period'=>$table['period'],
    'from'=>$table['from'],'to'=>$table['to']];
$dt_badges=['status'];
$dt_create=$can?($cfg['domain']==='softcopy'?'Softcopy Direct':'Add Document'):'';
$dt_folder_browser=$folder_browser;
$dt_folder_domain=$cfg['domain'];
$activeLayout=($table['layout']??'table')==='folder'?'folder':'table';
$layoutLink=static function($target) use($cfg,$folder_params,$folder_value) {
    $params=array_merge($folder_params,[
        'folder'=>$folder_value,'layout'=>$target,'page'=>1
    ]);
    return site_url('documents/'.$cfg['domain']).'?'.http_build_query($params);
};
?>
<nav class="tab-bar" aria-label="Document types">
<?php foreach (['hardcopy'=>'Hardcopy Documents','softcopy'=>'Softcopy Documents'] as $kind=>$label):
 if (isset($permissions['*']) || !empty($permissions[$kind]['view'])): ?>
<a class="tab-link <?= $cfg['domain']===$kind?'active':'' ?>" href="<?= html_escape(site_url('documents/'.$kind).'?'.http_build_query(['layout'=>$activeLayout])) ?>" <?= $cfg['domain']===$kind?'aria-current="page"':'' ?>>
<?= html_escape($label) ?></a><?php endif; endforeach; ?></nav>

<div class="document-layout-bar">
    <div class="document-layout-description">
        <strong><i class="fa-solid fa-folder-open" aria-hidden="true"></i> Document Browser</strong>
        <small>Choose a layout to browse or manage your records.</small>
    </div>
    <nav class="document-layout-switch" aria-label="Choose document view">
        <a href="<?= html_escape($layoutLink('table')) ?>"
           class="document-layout-choice <?= $activeLayout==='table'?'is-active':'' ?>"
           aria-label="Switch to table view" <?= $activeLayout==='table'?'aria-current="page"':'' ?>>
            <span class="document-layout-choice-icon"><i class="fa-solid fa-table-list" aria-hidden="true"></i></span>
            <span class="document-layout-choice-copy">Table</span>
        </a>
        <a href="<?= html_escape($layoutLink('folder')) ?>"
           class="document-layout-choice <?= $activeLayout==='folder'?'is-active':'' ?>"
           aria-label="Switch to folder tree view" <?= $activeLayout==='folder'?'aria-current="page"':'' ?>>
            <span class="document-layout-choice-icon"><i class="fa-solid fa-folder-tree" aria-hidden="true"></i></span>
            <span class="document-layout-choice-copy">Folder</span>
        </a>
    </nav>
</div>
<div class="document-register-listing" data-doc-domain="<?= html_escape($cfg['domain']) ?>" data-layout="<?= html_escape($activeLayout) ?>">
<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir',
'dt_sortable','dt_filters','dt_filter_values','dt_date_filters','dt_badges','dt_create','dt_columns','dt_rows',
'dt_extra_params','dt_folder_browser','dt_folder_domain'
)); ?>
</div>
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
<form id="editForm" method="post" enctype="multipart/form-data"
 action="<?= $cfg['domain']==='softcopy' ? site_url('documents/softcopy/direct') : site_url('documents/hardcopy/save') ?>"
 <?= $cfg['domain']==='softcopy'?'data-softcopy-direct="yes"':'data-hardcopy-upsert' ?>
 data-confirm="<?= $cfg['domain']==='softcopy'?'Approve and apply this softcopy action directly?':'Save document metadata?' ?>">
 <div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">Add Document</h2>
 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
 <div class="modal-body"><div class="row g-3">
   <input type="hidden" name="id" value=""><input type="hidden" name="version" value="">
   <?php if ($cfg['domain']==='softcopy'): ?>
       <?php $this->load->view('pages/request/softcopy_fields',[
           'softcopy_options'=>$softcopy_options,
           'category_options'=>$category_options,
           'softcopyDirect'=>TRUE
       ]); ?>
   <?php else: ?>
       <?php $this->load->view('pages/hardcopy_document/modal_action/upsert', [
           'options'=>$options,'user'=>$user,'is_administrator'=>$is_administrator
       ]); ?>
   <?php endif; ?>
 </div></div>
 <input type="hidden" name="confirmed" value="no">
 <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
 <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
 <button class="btn btn-primary" type="submit"><?= $cfg['domain']==='softcopy'?'Approve Directly':'Save Document' ?></button></div>
</form></div></div></div>

<?php if ($cfg['domain']==='softcopy'): ?>
<div class="modal fade file-history-saas" data-bs-backdrop="static" data-bs-keyboard="false" id="fileHistoryModal" tabindex="-1" aria-labelledby="fileHistoryTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header file-history-header">
      <div class="d-flex align-items-center gap-3">
        <span class="file-history-header-icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
        <div><span class="file-history-eyebrow">CONTROLLED DIGITAL DOCUMENT</span>
          <h2 class="modal-title h5 mb-0" id="fileHistoryTitle">Document File History</h2></div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body file-history-body">
      <p class="small text-secondary mb-3">Revision files available within your document permissions.</p>
      <div id="fileHistoryList" class="file-history-list"></div>
    </div>
    <div class="modal-footer file-history-footer">
      <span class="small text-secondary"><i class="fa-solid fa-lock me-1" aria-hidden="true"></i> Authorized files only</span>
      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    </div>
  </div></div>
</div>
<?php if ($can && (isset($permissions['*']) || !empty($permissions['files']['upload']))): ?>
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="uploadModal" tabindex="-1" aria-labelledby="uploadTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" enctype="multipart/form-data" action="<?= site_url('files/upload') ?>"
        data-confirm="Upload and record this controlled revision?">
    <div class="modal-header"><h2 class="modal-title fs-6" id="uploadTitle">Upload Controlled Revision</h2>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <p id="uploadDocumentName" class="small text-secondary"></p>
      <input type="hidden" name="document_id" id="uploadDocumentId">
      <label class="form-label" for="uploadLevel">New Revision Level</label>
      <input class="form-control mb-3" id="uploadLevel" name="new_revision_level" required maxlength="30">
      <label class="form-label" for="uploadPages">Page Count</label>
      <input class="form-control mb-3" id="uploadPages" type="number" name="page_number" min="1" value="1">
      <label class="form-label" for="uploadReason">Reason <span class="optional-label">(optional)</span></label>
      <textarea class="form-control mb-3" id="uploadReason" name="reason" rows="2"></textarea>
      <label class="form-label" for="uploadFile">File (15 MB maximum)</label>
      <input class="form-control" type="file" id="uploadFile" name="attachment"
        accept=".pdf,.txt,.jpg,.jpeg,.png,.docx,.xlsx" required>
    </div>
    <input type="hidden" name="confirmed" value="no">
    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
      value="<?= $this->security->get_csrf_hash() ?>">
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-primary">Upload Revision</button></div>
  </form></div></div>
</div>
<?php endif; ?>
<?php endif; ?>
