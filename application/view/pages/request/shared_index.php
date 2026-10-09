<?php
$tabs=[
 'softcopy'=>'Softcopy Requests','hardcopy'=>'Hardcopy Requests','hardcopy-transfer'=>'Hardcopy Transfer',
 'access-grant'=>'Document Access Grants','document-assign'=>'Document Assignments'
];
$typeMap=[
 'softcopy'=>['softcopy_create'=>'Create','softcopy_revise'=>'Revise','softcopy_cancel'=>'Cancel'],
 'hardcopy'=>['hardcopy_create'=>'Create','hardcopy_update'=>'Update','disposal'=>'Dispose'],
 'hardcopy-transfer'=>['transfer'=>'Transfer'],
 'access-grant'=>['access'=>'Grant Access'],
 'document-assign'=>['assignment'=>'Assign']
];
$dt_columns=['reference'=>'Reference','type'=>'Action','subject'=>'Subject',
    'requester'=>'Requester','status'=>'Status','updated'=>'Updated'];
$dt_rows=[];
foreach ($rows as $row) {
 $payload=json_decode($row['payload'],TRUE)?:[];
 $entries=$history[$row['id']]??[];
 $historyText=[];
 foreach ($entries as $h) {
    $historyText[]=ucfirst($h['action']).' — '.$h['user_name'].' — '.$h['created_at'].
      (!empty($h['comments'])?' — '.$h['comments']:'');
 }
 $display=[
 'Reference'=>$row['reference'],'Action'=>ucwords(str_replace('_',' ',$row['type'])),
 'Subject'=>$payload['subject']??'','Remarks'=>$payload['remarks']??'',
 'Requester'=>$row['requester_name'],'Status'=>$row['status'],
 'Workflow History'=>implode("\n",$historyText)?:'No decisions yet',
 'Created At'=>$row['created_at']
 ];
 $record=[
 'id'=>$row['id'],'type'=>$row['type'],'subject'=>$payload['subject']??'',
 'remarks'=>$payload['remarks']??'','title'=>$payload['title']??'',
 'document_number'=>$payload['document_number']??'',
 'category_id'=>$payload['category_id']??'','softcopy_id'=>$row['softcopy_id']??'',
 'hardcopy_id'=>$row['hardcopy_id']??'','recipient_id'=>$payload['recipient_id']??'',
 'expires_at'=>$payload['expires_at']??'',
 'destination_location_id'=>$payload['destination_location_id']??''
 ];
 $buttons=[['type'=>'view']];
 $canEdit=isset($permissions['*']) || !empty($permissions['requests']['edit']);
 if (!$task && in_array($row['status'],['draft','returned'],TRUE)) {
    if ($canEdit) $buttons[]=['type'=>'edit'];
    $buttons[]=['type'=>'action','url'=>'my-requests/'.$tab.'/submit',
       'label'=>'Submit','description'=>'Submit this request to the published approval workflow.',
       'icon'=>'fa-solid fa-paper-plane'];
 }
 if (!$task && $row['status']==='draft') $buttons[]=['type'=>'action',
    'url'=>'my-requests/'.$tab.'/cancel','label'=>'Cancel',
    'description'=>'Cancel this draft request.','icon'=>'fa-solid fa-xmark'];
 if ($task) foreach (['approved'=>'Approve','rejected'=>'Reject','returned'=>'Return'] as $decision=>$label) {
    $buttons[]=['type'=>'action','url'=>'my-tasks/'.$tab.'/decide',
       'decision'=>$decision,'label'=>$label,
       'description'=>'Record '.$label.' for this workflow step.',
       'icon'=>$decision==='approved'?'fa-solid fa-check':'fa-solid fa-arrow-rotate-left'];
 }
 $dt_rows[]=['id'=>$row['id'],'cells'=>[
   'reference'=>$row['reference'],'type'=>ucwords(str_replace('_',' ',$row['type'])),
   'subject'=>$payload['subject']??'','requester'=>$row['requester_name'],
   'status'=>$row['status'],'updated'=>$row['updated_at']
 ],'display'=>$display,'record'=>$record,'buttons'=>$buttons];
}
$dt_q=$table['q'];$dt_filter=$table['status'];
$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort='updated_at';$dt_dir='desc';$dt_sortable=[];$dt_filter_values=['status'=>$dt_filter];
$dt_filters=['status'=>[''=>'All Statuses','draft'=>'Draft','submitted'=>'Submitted',
 'approved'=>'Approved','rejected'=>'Rejected','returned'=>'Returned','cancelled'=>'Cancelled']];
$dt_badges=['status'];$dt_create=$task?'':'New Request';
$dt_path=($task?'my-tasks/':'my-requests/').$tab;
?>
<div class="page-heading"><div><span class="eyebrow">Request Center</span>
<h1><?= html_escape($task?'My Tasks':'My Requests') ?></h1>
<p><?= $task?'Decisions assigned to your user or role':'Draft, submit and track document requests' ?></p>
</div></div>
<nav class="tab-bar" aria-label="Request category tabs">
<?php foreach ($tabs as $slug=>$name): ?>
<a class="tab-link <?= $tab===$slug?'active':'' ?>"
href="<?= site_url(($task?'my-tasks/':'my-requests/').$slug) ?>"><?= html_escape($name) ?></a>
<?php endforeach; ?></nav>
<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir',
'dt_sortable','dt_filters','dt_filter_values','dt_badges','dt_create','dt_columns','dt_rows'
)); ?>
<?php if (!$task): ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
<form id="editForm" method="post" action="<?= site_url('my-requests/'.$tab.'/save') ?>"
data-confirm="Save request draft?">
<div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">New Request</h2>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><div class="row g-3">
<input type="hidden" name="id" value="">
<div class="col-md-6"><label class="form-label" for="reqType">Request Action</label>
<select class="form-select" name="type" id="reqType" required>
<?php foreach ($typeMap[$tab] as $key=>$name): ?>
<option value="<?= html_escape($key) ?>"><?= html_escape($name) ?></option>
<?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="reqSubject">Subject</label>
<input class="form-control" name="subject" id="reqSubject" maxlength="255" required></div>
<div class="col-md-6"><label class="form-label" for="reqSoftcopy">Softcopy Document</label>
<select class="form-select" name="softcopy_id" id="reqSoftcopy" data-searchable>
<option value="">Not selected</option>
<?php foreach ($softcopy_options as $item): ?>
<option value="<?= (int)$item['id'] ?>"><?= html_escape($item['document_number'].' · '.$item['title']) ?></option>
<?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="reqHardcopy">Hardcopy Document</label>
<select class="form-select" name="hardcopy_id" id="reqHardcopy" data-searchable>
<option value="">Not selected</option>
<?php foreach ($hardcopy_options as $item): ?>
<option value="<?= (int)$item['id'] ?>"><?= html_escape($item['title']) ?></option>
<?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="reqTitle">Proposed Document Title</label>
<input class="form-control" name="title" id="reqTitle" maxlength="255"></div>
<div class="col-md-6"><label class="form-label" for="reqNumber">Document Number</label>
<input class="form-control" name="document_number" id="reqNumber" maxlength="100"></div>
<div class="col-md-6"><label class="form-label" for="reqCategory">Category</label>
<select class="form-select" name="category_id" id="reqCategory" data-searchable>
<option value="">No category</option><?php foreach ($category_options as $opt): ?>
<option value="<?= (int)$opt['id'] ?>"><?= html_escape($opt['name']) ?></option>
<?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="reqUser">Recipient / Assigned User</label>
<select class="form-select" name="recipient_id" id="reqUser" data-searchable>
<option value="">Not selected</option><?php foreach ($users as $opt): ?>
<option value="<?= (int)$opt['id'] ?>"><?= html_escape($opt['name']) ?></option>
<?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="reqExpires">Access Expires At</label>
<input class="form-control" name="expires_at" type="date" id="reqExpires"></div>
<div class="col-md-6"><label class="form-label" for="reqDest">Destination Location</label>
<select class="form-select" name="destination_location_id" id="reqDest" data-searchable>
<option value="">Not selected</option><?php foreach ($location_options as $opt): ?>
<option value="<?= (int)$opt['id'] ?>"><?= html_escape($opt['name']) ?></option>
<?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label" for="reqRemark">Remarks <span class="optional-label">(optional)</span></label>
<textarea class="form-control" id="reqRemark" name="remarks" rows="3"></textarea></div>
</div></div>
<input type="hidden" name="confirmed" value="no">
<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
<div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
<button class="btn btn-primary" type="submit">Save Draft</button></div>
</form></div></div></div>
<?php endif; ?>
