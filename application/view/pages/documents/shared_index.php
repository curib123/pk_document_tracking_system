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
 $rec=[];
 foreach (array_merge(['id'],$cfg['fields']) as $field) $rec[$field]=$r[$field]??'';
 $buttons=[['type'=>'view']];
 if ($can) $buttons[]=['type'=>'edit'];
 if ($cfg['domain']==='softcopy') {
     if ($can && (isset($permissions['*']) || !empty($permissions['files']['upload']))) {
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
 if ($can && $r['status']!=='disposed') $buttons[]=['type'=>'action',
     'url'=>'documents/'.$cfg['domain'].'/dispose','label'=>'Dispose',
     'description'=>'Permanently mark this document disposed, preserving the status history.',
     'icon'=>'fa-solid fa-trash-can'];
 $dt_rows[]=['id'=>$r['id'],'cells'=>$cells,'record'=>$rec,'display'=>$display,'buttons'=>$buttons];
}
$dt_path='documents/'.$cfg['domain'];$dt_q=$table['q'];$dt_filter=$table['status'];
$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort='updated_at';$dt_dir='desc';$dt_sortable=[];
$dt_filters=['status'=>[''=>'All Statuses','active'=>'Active','disposed'=>'Disposed','archived'=>'Archived']];
$dt_filter_values=['status'=>$dt_filter];
$dt_badges=['status'];
$dt_create=$can?'Add Document':'';
?>
<div class="page-heading"><div>
<span class="eyebrow">System Documents</span><h1><?= html_escape($title) ?></h1>
<p>Controlled <?= html_escape($cfg['domain']) ?> register based on the original database.</p>
</div></div>
<nav class="tab-bar" aria-label="Document types">
<?php foreach (['hardcopy'=>'Hardcopy Documents','softcopy'=>'Softcopy Documents'] as $kind=>$label):
 if (isset($permissions['*']) || !empty($permissions[$kind]['view'])): ?>
<a class="tab-link <?= $cfg['domain']===$kind?'active':'' ?>" href="<?= site_url('documents/'.$kind) ?>">
<?= html_escape($label) ?></a><?php endif; endforeach; ?></nav>
<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir',
'dt_sortable','dt_filters','dt_filter_values','dt_badges','dt_create','dt_columns','dt_rows'
)); ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
<form id="editForm" method="post" action="<?= site_url('documents/'.$cfg['domain'].'/save') ?>"
 data-confirm="Save document metadata?">
 <div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">Add Document</h2>
 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
 <div class="modal-body"><div class="row g-3">
   <input type="hidden" name="id" value="">
   <?php foreach ($cfg['fields'] as $field):
    $references=['area_id'=>'areas','specific_id'=>'specifics','asset_id'=>'assets',
      'location_id'=>'locations','category_id'=>'categories','holder_id'=>'users'];
    $label=ucwords(str_replace('_',' ',$field));
    if (isset($references[$field])):
        $collection=$references[$field]; ?>
    <div class="col-md-6"><label class="form-label" for="d-<?= html_escape($field) ?>"><?= html_escape($label) ?></label>
     <select class="form-select" id="d-<?= html_escape($field) ?>" name="<?= html_escape($field) ?>" data-searchable>
       <option value="">Select <?= html_escape($label) ?></option>
       <?php foreach ($options[$collection] as $option): ?>
       <option value="<?= (int)$option['id'] ?>"><?= html_escape($option['name']) ?></option>
       <?php endforeach; ?>
     </select></div>
    <?php elseif ($field==='retention_enabled'): ?>
       <div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="retention_enabled" value="1"> Enable retention</label></div>
    <?php else: ?>
    <div class="col-md-6"><label class="form-label" for="d-<?= html_escape($field) ?>"><?= html_escape($label) ?></label>
     <input class="form-control" id="d-<?= html_escape($field) ?>" name="<?= html_escape($field) ?>"
       <?= str_contains($field,'_date')?'type="date"':'maxlength="255"' ?>
       <?= $field==='title' || $field==='document_number'?'required':'' ?>>
    </div>
    <?php endif; endforeach; ?>
    <div class="col-12"><label class="form-label" for="creationReason">Reason <span class="optional-label">(optional)</span></label>
     <textarea class="form-control" id="creationReason" name="creation_reason" rows="2"></textarea></div>
 </div></div>
 <input type="hidden" name="confirmed" value="no">
 <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
 <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
 <button class="btn btn-primary" type="submit">Save Document</button></div>
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
