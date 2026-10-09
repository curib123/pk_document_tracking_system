<?php
$permissions=$permissions??[];
$can=isset($permissions['*']) || !empty($permissions[$cfg['domain']]['direct']);
$labels=['title'=>'Title','document_number'=>'Document Number','series_number'=>'Series',
'category_name'=>'Category','area_name'=>'Area','specific_name'=>'Specific',
'asset_name'=>'Asset','location_name'=>'Location','sequence_number'=>'Sequence',
'holder_name'=>'Holder','status'=>'Status','version'=>'Version','updated_at'=>'Updated'];
$fields=$cfg['domain']==='hardcopy'
    ? ['title','area_name','specific_name','asset_name','location_name','sequence_number','holder_name','status','updated_at']
    : ['document_number','title','category_name','series_number','status','updated_at'];
$dt_columns=[]; foreach ($fields as $field) $dt_columns[$field]=$labels[$field]??$field;
$dt_rows=[];
foreach ($rows as $r) {
 $cells=[];$display=[];
 foreach ($dt_columns as $field=>$label) {
    $val=$r[$field]??'';
    $cells[$field]=$val;$display[$label]=$val;
 }
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
     if (!empty($latest_files[$r['id']]) && !empty($file_access[$r['id']])) {
         $historyEntries=[];
         foreach ($file_histories[$r['id']]??[] as $item) {
             $historyEntries[]=[
                 'name'=>$item['original_name'],
                 'detail'=>$item['first_name'].' '.$item['last_name'].' — '.$item['created_at'],
                 'url'=>site_url('files/download/'.$item['id'])
             ];
         }
         $buttons[]=['type'=>'history','files'=>$historyEntries];
         $buttons[]=['type'=>'download','url'=>'files/download/'.$latest_files[$r['id']]['id']];
     }
 }
 if ($canEdit && (isset($permissions['*']) || !empty($permissions['disposal']['direct']))) $buttons[]=['type'=>'action',
     'url'=>'documents/'.$cfg['domain'].'/dispose','label'=>'Dispose',
     'disposal'=>TRUE,
     'description'=>'Choose a disposal reason and confirm this action.',
     'icon'=>'fa-solid fa-trash-can'];
 $dt_rows[]=['id'=>$r['id'],'cells'=>$cells,'record'=>$rec,'display'=>$display,'buttons'=>$buttons];
}
$dt_path='documents/'.$cfg['domain'];
$dt_extra_params=['folder'=>$folder_value,'layout'=>$table['layout']];$dt_q=$table['q'];$dt_filter=$table['status'];
$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort=$table['sort'];$dt_dir=strtolower($table['dir']);
$dt_sortable=['title','document_number','sequence_number','status','updated_at'];
$dt_date_filters=TRUE;
$dt_filters=['status'=>[''=>'All Statuses','active'=>'Active','disposed'=>'Disposed','archived'=>'Archived','cancelled'=>'Cancelled']];
$dt_filters['owner']=[''=>'All Owners']+array_column($owner_options,'name','id');
$dt_filter_values=['status'=>$dt_filter,'owner'=>$table['owner'],'from'=>$table['from'],'to'=>$table['to']];
$dt_badges=['status'];
$dt_create=$can?($cfg['domain']==='softcopy'?'Softcopy Direct':'Add Document'):'';
?>
<div class="page-heading"><div>
<span class="eyebrow">System Documents</span><h1><?= html_escape($title) ?></h1>
<p>Controlled <?= html_escape($cfg['domain']) ?> register based on the original database.</p>
</div></div>
<nav class="tab-bar" aria-label="Document types">
<?php foreach (['hardcopy'=>'Hardcopy Documents','softcopy'=>'Softcopy Documents'] as $kind=>$label):
 if (isset($permissions['*']) || !empty($permissions[$kind]['view'])): ?>
<a class="tab-link <?= $cfg['domain']===$kind?'active':'' ?>" href="<?= site_url('documents/'.$kind) ?>" <?= $cfg['domain']===$kind?'aria-current="page"':'' ?>>
<?= html_escape($label) ?></a><?php endif; endforeach; ?></nav>
<?php $this->load->view('reusable_components/folder_browser',[
    'folder_browser'=>$folder_browser,'folder_base'=>$folder_base,
    'folder_params'=>$folder_params,'folder_page'=>$folder_page,'domain'=>$cfg['domain']
]); ?>
<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir',
'dt_sortable','dt_filters','dt_filter_values','dt_date_filters','dt_badges','dt_create','dt_columns','dt_rows',
'dt_extra_params'
)); ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
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
<div class="modal fade" id="fileHistoryModal" tabindex="-1" aria-labelledby="fileHistoryTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-6" id="fileHistoryTitle">Document File History</h2>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body" id="fileHistoryList"></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>
<?php if ($can && (isset($permissions['*']) || !empty($permissions['files']['upload']))): ?>
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadTitle" aria-hidden="true">
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
