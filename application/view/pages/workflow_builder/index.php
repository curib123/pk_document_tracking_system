<?php
$types=['softcopy_create'=>'Softcopy Create','softcopy_revise'=>'Softcopy Revise',
 'softcopy_cancel'=>'Softcopy Cancel','hardcopy_create'=>'Hardcopy Create',
 'hardcopy_update'=>'Hardcopy Update','transfer'=>'Hardcopy Transfer',
 'assignment'=>'Document Assign','access'=>'Access Grant','disposal'=>'Disposal'];
$dt_columns=['name'=>'Workflow','type'=>'Request Type','version'=>'Latest Version','status'=>'Version Status','active'=>'Active'];
$dt_rows=[];
foreach($rows as $r){
 $state=$r['version_status']??'not configured';
 $graph=json_decode($r['graph']??'{"steps":[]}',TRUE)?:['steps'=>[]];
 $steps=$graph['steps']??[];
 $dt_rows[]=['id'=>$r['id'],'cells'=>[
   'name'=>$r['name'],'type'=>$types[$r['request_type']]??$r['request_type'],
   'version'=>'v'.($r['version_number']??0),'status'=>$state,
   'active'=>$r['active']?'active':'0'
 ],'record'=>['id'=>$r['id'],'name'=>$r['name'],'workflow_key'=>$r['workflow_key'],
    'description'=>$r['description'],'request_type'=>$r['request_type']],
 'display'=>['Workflow'=>$r['name'],'Request Type'=>$r['request_type'],
    'Latest Version'=>$r['version_number']??0,'Status'=>$state,
    'Approval Steps'=>implode(' → ',array_column($steps,'name'))],
 'buttons'=>[['type'=>'view'],['type'=>'edit']]];
}
$dt_path='admin/workflows';$dt_q=$table['q'];$dt_filter=$table['status'];
$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort='name';$dt_dir='asc';$dt_sortable=[];
$dt_filters=['status'=>[''=>'All Statuses','1'=>'Active','0'=>'Inactive']];
$dt_filter_values=['status'=>$dt_filter];$dt_badges=['active','status'];
$dt_create='New Workflow';
?>
<div class="page-heading"><div><span class="eyebrow">Administration</span><h1>Workflow Builder</h1>
<p>Configure who receives each request, in order. Each approver must approve before the request moves to the next step.</p></div></div>
<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir',
'dt_sortable','dt_filters','dt_filter_values','dt_badges','dt_create','dt_columns','dt_rows'
)); ?>
<div class="workspace-card mt-4"><div class="workspace-card-header">
<strong>Request Approval Route</strong><p class="text-secondary small mb-0">1. Choose the approver at each stage · 2. Arrange the pass-to order · 3. Publish the workflow version</p>
</div><div class="accordion accordion-flush" id="wfAccordion">
<?php foreach($rows as $r):
 $steps=(json_decode($r['graph']??'{"steps":[]}',TRUE)['steps']??[]);
 $draft=($r['version_status']??'')==='draft';
 $versionId=(int)($r['latest_version_id']??0); ?>
<div class="accordion-item"><h2 class="accordion-header">
<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
data-bs-target="#wf-<?= (int)$r['id'] ?>">
<?= html_escape($r['name']) ?> — v<?= (int)$r['version_number'] ?> (<?= html_escape($r['version_status']) ?>)
</button></h2>
<div class="accordion-collapse collapse" id="wf-<?= (int)$r['id'] ?>" data-bs-parent="#wfAccordion">
<div class="accordion-body">
<?php foreach($steps as $index=>$step):
 $approver=$step['approver']??[]; ?>
<div class="workflow-step d-flex align-items-center justify-content-between gap-2">
<div><strong><?= $index+1 ?>. <?= html_escape($step['name']??'Approval') ?></strong>
<?php
$approverType=$approver['type']??'';
$approverLabel=$approver['label']??'';
if ($approverType==='user') {
    foreach ($users as $option) if ((int)$option['id']===(int)($approver['value']??0)) $approverLabel=$option['name'];
} elseif ($approverType==='role') {
    foreach ($roles as $option) if ((int)$option['id']===(int)($approver['value']??0)) $approverLabel=$option['name'];
} elseif ($approverType==='requester_leader') $approverLabel='Requester’s leader';
elseif ($approverType==='requester') $approverLabel='Requester account';
?>
<small class="text-secondary d-block">
<i class="fa-solid fa-arrow-right-arrow-left me-1" aria-hidden="true"></i>
Send to <?= html_escape($approverLabel) ?> (<?= html_escape(ucwords(str_replace('_',' ',$approverType))) ?>)
</small></div>
<?php if ($draft): ?>
<div class="d-flex align-items-center gap-1">
<button type="button" class="btn-icon js-edit"
  data-bs-toggle="modal" data-bs-target="#stepModal" data-target="#stepForm"
  data-record="<?= html_escape(json_encode([
     'workflow_version_id'=>$versionId,'step_key'=>$step['key']??'',
     'name'=>$step['name']??'',
     'approver_type'=>$approver['type']??'user',
     'approver_user_id'=>($approver['type']??'')==='user' ? ($approver['value']??'') : '',
     'approver_role_id'=>($approver['type']??'')==='role' ? ($approver['value']??'') : ''
   ])) ?>"
  data-title="Edit Approval Step" title="Edit Approver" aria-label="Edit Approver">
  <i class="fa-solid fa-pen"></i>
</button>
<?php foreach (['up'=>'Move earlier','down'=>'Move later'] as $direction=>$description): ?>
<?php if (($direction==='up' && $index>0) || ($direction==='down' && $index<count($steps)-1)): ?>
<button type="button" class="btn-icon js-action"
 aria-label="<?= $description ?>" title="<?= $description ?>"
 data-bs-toggle="modal" data-bs-target="#actionModal"
 data-url="<?= site_url('admin/workflows/step/move') ?>"
 data-id="<?= $versionId ?>"
 data-step-key="<?= html_escape($step['key']??'') ?>"
 data-direction="<?= $direction ?>"
 data-title="<?= $description ?>"
 data-description="Move this approver step <?= $direction==='up'?'earlier':'later' ?> in the request approval sequence.">
 <i class="fa-solid fa-arrow-<?= $direction==='up'?'up':'down' ?>"></i>
</button>
<?php endif; ?>
<?php endforeach; ?>
<button type="button" class="btn-icon js-action" aria-label="Remove step" title="Remove Step"
 data-bs-toggle="modal" data-bs-target="#actionModal"
 data-url="<?= site_url('admin/workflows/step/remove') ?>"
 data-id="<?= $versionId ?>" data-step-key="<?= html_escape($step['key']??'') ?>"
 data-title="Remove Approval Step"
 data-description="Remove this step from the draft. The remaining steps will be renumbered.">
 <i class="fa-solid fa-trash-can"></i>
</button></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php if(!$steps): ?><p class="text-muted small">No steps yet. Add at least one step to publish.</p><?php endif; ?>
<div class="d-flex gap-2 flex-wrap mt-3">
<?php if($draft): ?>
<button class="btn btn-outline-primary btn-sm js-edit" type="button"
 data-bs-toggle="modal" data-bs-target="#stepModal" data-target="#stepForm"
 data-record="<?= html_escape(json_encode(['workflow_version_id'=>$versionId])) ?>"
 data-title="Add Approval Step"><i class="fa-solid fa-plus me-1"></i> Add Step</button>
<?php if($steps): ?>
<button class="btn btn-primary btn-sm js-action" type="button"
 data-bs-toggle="modal" data-bs-target="#actionModal"
 data-url="<?= site_url('admin/workflows/publish') ?>" data-id="<?= $versionId ?>"
 data-title="Publish Workflow" data-description="Make this version the active approval workflow for this request type.">
Publish Version</button><?php endif; ?>
<?php else: ?>
<button class="btn btn-light btn-sm js-action" type="button"
 data-bs-toggle="modal" data-bs-target="#actionModal"
 data-url="<?= site_url('admin/workflows/clone') ?>" data-id="<?= (int)$r['id'] ?>"
 data-title="Clone Workflow" data-description="Create an editable draft copy of the latest published graph.">
<i class="fa-solid fa-code-branch me-1"></i> Clone New Version</button>
<?php endif; ?>
</div></div></div></div>
<?php endforeach; ?></div></div>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
<form id="editForm" action="<?= site_url('admin/workflows/save') ?>" method="post" data-confirm="Save workflow?">
<div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">Workflow</h2>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body">
<input type="hidden" name="id" value="">
<div class="mb-3"><label class="form-label" for="wName">Name</label><input class="form-control"
id="wName" name="name" required maxlength="150"></div>
<div class="mb-3"><label class="form-label" for="wKey">Workflow Key</label><input class="form-control"
id="wKey" name="workflow_key" required pattern="[a-z0-9_]{3,80}"></div>
<div class="mb-3"><label class="form-label" for="wType">Request Type</label>
<select class="form-select" name="request_type" id="wType" required>
<?php foreach($types as $key=>$label): ?><option value="<?= html_escape($key) ?>"><?= html_escape($label) ?></option>
<?php endforeach; ?></select></div>
<div><label class="form-label" for="wDescription">Description <span class="optional-label">(optional)</span></label>
<textarea class="form-control" id="wDescription" name="description" rows="2"></textarea></div>
</div><input type="hidden" name="confirmed" value="no">
<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
<div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
<button class="btn btn-primary" type="submit">Save Workflow</button></div></form></div></div></div>
<div class="modal fade" id="stepModal" tabindex="-1" aria-labelledby="stepTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
<form id="stepForm" action="<?= site_url('admin/workflows/step/save') ?>" method="post"
 data-confirm="Add this approval step?">
<div class="modal-header"><h2 class="modal-title fs-6" id="stepTitle">Add Approval Step</h2>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body">
<input type="hidden" name="workflow_version_id" value="">
<input type="hidden" name="step_key" value="">
<div class="mb-3"><label class="form-label" for="sName">Step Name</label>
<input class="form-control" id="sName" name="name" maxlength="120" required></div>
<div class="mb-3"><label class="form-label" for="sType">Approver Type</label>
<select class="form-select" name="approver_type" id="sType">
<option value="user">Specific User</option><option value="role">Role</option>
<option value="requester_leader">Requester Leader</option>
<option value="requester">Requester Account</option></select></div>
<div data-approver-option="user"><label class="form-label" for="sUser">Specific User</label>
<select class="form-select" name="approver_user_id" id="sUser" data-searchable>
<option value="">Select active user</option>
<?php foreach($users as $u): ?>
<option value="<?= (int)$u['id'] ?>"><?= html_escape($u['name']) ?></option>
<?php endforeach; ?></select></div>
<div data-approver-option="role" hidden><label class="form-label" for="sRole">Approver Role</label>
<select class="form-select" name="approver_role_id" id="sRole" disabled data-searchable>
<option value="">Select active role</option>
<?php foreach($roles as $r): ?>
<option value="<?= (int)$r['id'] ?>"><?= html_escape($r['name']) ?></option>
<?php endforeach; ?></select></div>
<div class="small text-secondary" id="approverAutoInfo" hidden>
Requester and leader approvers are determined automatically when the request is submitted.
</div></div><input type="hidden" name="confirmed" value="no">
<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
<div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
<button class="btn btn-primary" type="submit">Save Approval Step</button></div></form></div></div></div>
