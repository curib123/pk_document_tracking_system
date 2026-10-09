<?php
$types=['softcopy_create'=>'Softcopy Create','softcopy_revise'=>'Softcopy Revise',
 'softcopy_cancel'=>'Softcopy Cancel','hardcopy_create'=>'Hardcopy Create',
 'hardcopy_update'=>'Hardcopy Update','transfer'=>'Hardcopy Transfer',
 'assignment'=>'Document Assign','access'=>'Access Grant','disposal'=>'Disposal'];
$canEdit=isset($permissions['*']) || !empty($permissions['workflows']['edit']);
$dt_columns=['name'=>'Workflow','type'=>'Request Type','version'=>'Latest Version','status'=>'Version Status','active'=>'Status'];
$dt_rows=[];
foreach ($rows as $row) {
    $dt_rows[]=['id'=>$row['id'],'cells'=>[
        'name'=>$row['name'],'type'=>$types[$row['request_type']]??$row['request_type'],
        'version'=>'v'.$row['version_number'],'status'=>$row['version_status'],
        'active'=>$row['active']?'active':'0'],
        'display'=>['Workflow'=>$row['name'],'Request Type'=>$types[$row['request_type']]??$row['request_type'],
            'Latest Version'=>'v'.$row['version_number'],'Status'=>$row['version_status']],
        'record'=>[],'buttons'=>[['type'=>'steps']]];
}
$dt_path='admin/workflows';$dt_q=$table['q'];$dt_filter=$table['status'];
$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort=$table['sort'];$dt_dir=strtolower($table['dir']);$dt_sortable=['name','type','active'];
$dt_filters=['status'=>[''=>'All Statuses','1'=>'Active','0'=>'Inactive'],
    'type'=>[''=>'All Request Types']+$types,
    'publication'=>[''=>'All Versions','draft'=>'With Draft Versions','published'=>'With Published Versions']];
$dt_filter_values=['status'=>$dt_filter,'type'=>$table['type'],'publication'=>$table['publication']];
$dt_badges=['active','status'];$dt_create='';
$csrfName=$this->security->get_csrf_token_name();$csrfValue=$this->security->get_csrf_hash();
$approverLabels=['user'=>'By Specific User','role'=>'By Role',
    'requester_leader'=>"By Requester's Leader",'requester'=>'By Requester Themselves'];
?>
<div class="page-heading"><div><span class="eyebrow">Administration</span><h1>Workflow Builder</h1>
<p>Predefined Approval Workflows · Edit a draft, then publish it for future requests.</p></div></div>
<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir','dt_sortable',
'dt_filters','dt_filter_values','dt_badges','dt_create','dt_columns','dt_rows')); ?>
<?php foreach ($rows as $row):
    $versions=$row['versions']??[];
    $hasDraft=in_array('draft',array_column($versions,'status'),TRUE);
    $modalId='workflowStepsModal-'.(int)$row['id']; ?>
<div class="modal fade workflow-steps-modal" id="<?= $modalId ?>" tabindex="-1"
     aria-labelledby="<?= $modalId ?>Title" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl"><div class="modal-content">
  <div class="modal-header"><div><h2 class="modal-title fs-5" id="<?= $modalId ?>Title">Workflow Steps</h2>
   <p class="small text-secondary mb-0"><?= html_escape($row['name']) ?> · <?= html_escape($types[$row['request_type']]??$row['request_type']) ?></p></div>
   <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
   <?php if (!$versions): ?><div class="alert alert-warning">No workflow versions are configured. Restore the predefined workflow seed before accepting requests.</div>
   <?php else: ?>
   <div class="row g-3 mb-3 align-items-end"><div class="col-12 col-md-7">
    <label class="form-label" for="workflowVersion-<?= (int)$row['id'] ?>">Published and Draft Versions</label>
    <select class="form-select" id="workflowVersion-<?= (int)$row['id'] ?>" data-workflow-version>
     <?php foreach ($versions as $version): ?>
     <option value="<?= (int)$version['id'] ?>">Version <?= (int)$version['version_number'] ?> · <?= html_escape(ucfirst($version['status'])) ?><?= $version['is_default']?' · Current default':'' ?></option>
     <?php endforeach; ?>
    </select>
   </div><div class="col-12 col-md-5 text-md-end">
    <?php if ($canEdit && !$hasDraft): ?>
    <form method="post" action="<?= site_url('admin/workflows/clone') ?>" data-confirm="Create an editable copy of this workflow?">
     <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
     <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
     <button type="submit" class="btn btn-outline-primary">Create Draft Version</button>
    </form>
    <?php endif; ?>
   </div></div>
   <?php foreach ($versions as $versionIndex=>$version):
       $draft=$version['status']==='draft';
       $available=!$draft && $version['status']==='published' && $version['is_default'] && $row['active'];
       $graph=json_decode($version['graph'],TRUE);
       $steps=is_array($graph['steps']??NULL)?$graph['steps']:[];
       $graphHash=hash('sha256',$version['graph']);
       $newRecord=['workflow_version_id'=>(int)$version['id'],'graph_hash'=>$graphHash,
           'step_key'=>'','name'=>'','approver_type'=>'requester','approver_user_id'=>'','approver_role_id'=>'']; ?>
   <section data-workflow-version-panel="<?= (int)$version['id'] ?>" <?= $versionIndex?'hidden':'' ?>
       aria-labelledby="approvalHeading-<?= (int)$version['id'] ?>">
    <div class="workflow-version-heading d-flex justify-content-between align-items-start gap-3 flex-wrap">
     <div><h3 class="h6" id="approvalHeading-<?= (int)$version['id'] ?>">Approval Steps · Version <?= (int)$version['version_number'] ?></h3>
      <span class="badge <?= $draft?'text-bg-warning':'text-bg-success' ?>"><?= $draft?'Draft':'Published' ?></span>
      <span class="small ms-2"><?= $available?'Available for Request Approval':($draft?'Not available for requests until published':'Historical version · existing requests keep their snapshot') ?></span>
     </div>
     <?php if ($draft && $canEdit): ?>
     <button type="button" class="btn btn-primary btn-sm js-workflow-step" data-step-record="<?= html_escape(json_encode($newRecord)) ?>">Create New Step</button>
     <?php endif; ?>
    </div>
    <?php if (!$steps): ?><div class="alert alert-info mt-3">No approval steps yet. Add at least one step before publishing.</div><?php endif; ?>
    <ol class="workflow-step-list list-unstyled mt-3">
     <?php foreach ($steps as $index=>$step):
       $approver=$step['approver']??[];$type=$approver['type']??'';
       $target=$approver['label']??($approverLabels[$type]??'Approver unavailable');
       foreach (($type==='user'?$users:($type==='role'?$roles:[])) as $option)
           if ((int)$option['id']===(int)($approver['value']??0)) $target=$option['name'];
       $record=array_replace($newRecord,['step_key'=>$step['key']??'','name'=>$step['name']??'',
           'approver_type'=>$type,'approver_user_id'=>$type==='user'?$approver['value']:'',
           'approver_role_id'=>$type==='role'?$approver['value']:'']); ?>
     <li class="workflow-step d-flex align-items-start justify-content-between gap-3 flex-wrap">
      <div><strong><?= $index+1 ?>. <?= html_escape($step['name']??'Approval') ?></strong>
       <p class="small text-secondary mb-1"><?= html_escape($approverLabels[$type]??'Invalid approver') ?> · <?= html_escape($target) ?></p>
       <span class="small"><?= $draft?'Draft step':'Published step' ?></span>
      </div>
      <?php if ($draft && $canEdit): ?>
      <div class="d-flex gap-1 flex-wrap">
       <button type="button" class="btn btn-sm btn-outline-secondary js-workflow-step" data-step-record="<?= html_escape(json_encode($record)) ?>">Edit</button>
       <?php foreach (['up'=>'Move Up','down'=>'Move Down','remove'=>'Remove'] as $direction=>$label):
         if (($direction==='up' && $index===0) || ($direction==='down' && $index===count($steps)-1)) continue; ?>
       <form method="post" action="<?= site_url('admin/workflows/step/'.($direction==='remove'?'remove':'move')) ?>" data-confirm="<?= html_escape($label) ?> this approval step?">
        <input type="hidden" name="id" value="<?= (int)$version['id'] ?>">
        <input type="hidden" name="step_key" value="<?= html_escape($step['key']??'') ?>">
        <input type="hidden" name="direction" value="<?= $direction ?>"><input type="hidden" name="graph_hash" value="<?= $graphHash ?>">
        <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
        <button class="btn btn-sm <?= $direction==='remove'?'btn-outline-danger':'btn-light' ?>" type="submit"><?= $label ?></button>
       </form>
       <?php endforeach; ?>
      </div><?php endif; ?>
     </li><?php endforeach; ?>
    </ol>
    <?php if ($draft && $canEdit): ?>
    <form method="post" action="<?= site_url('admin/workflows/publish') ?>" data-confirm="Publish these steps as the default approval route for future requests?">
     <input type="hidden" name="id" value="<?= (int)$version['id'] ?>"><input type="hidden" name="graph_hash" value="<?= $graphHash ?>">
     <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
     <button class="btn btn-success" type="submit" <?= !$steps?'disabled':'' ?>>Publish Approval Steps</button>
    </form><?php endif; ?>
   </section><?php endforeach; endif; ?>
  </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
 </div></div>
</div>
<?php endforeach; ?>
<?php if ($canEdit): ?>
<div class="modal fade" id="stepModal" tabindex="-1" aria-labelledby="stepTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <form id="stepForm" method="post" action="<?= site_url('admin/workflows/step/save') ?>" data-confirm="Save this draft approval step?">
   <div class="modal-header"><h2 class="modal-title fs-5" id="stepTitle">Create New Step</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
   <div class="modal-body"><input type="hidden" name="workflow_version_id"><input type="hidden" name="step_key"><input type="hidden" name="graph_hash">
    <label class="form-label" for="stepName">Step Name</label><input class="form-control mb-3" id="stepName" name="name" maxlength="120" required>
    <label class="form-label" for="stepApproverType">Who Approves</label>
    <select id="stepApproverType" class="form-select mb-3" name="approver_type">
     <?php foreach ($approverLabels as $key=>$label): ?><option value="<?= $key ?>"><?= html_escape($label) ?></option><?php endforeach; ?>
    </select>
    <div id="stepUserField"><label class="form-label" for="stepApproverUser">Specific User</label>
     <select id="stepApproverUser" class="form-select" name="approver_user_id" data-searchable><option value="">Select a user</option>
      <?php foreach ($users as $option): ?><option value="<?= (int)$option['id'] ?>"><?= html_escape($option['name']) ?></option><?php endforeach; ?>
     </select></div>
    <div id="stepRoleField" hidden><label class="form-label" for="stepApproverRole">Role</label>
     <select id="stepApproverRole" class="form-select" name="approver_role_id" data-searchable><option value="">Select a role</option>
      <?php foreach ($roles as $option): ?><option value="<?= (int)$option['id'] ?>"><?= html_escape($option['name']) ?></option><?php endforeach; ?>
     </select></div>
    <p class="form-text mt-3">The requester's account or leader is resolved from current account records when the request is submitted.</p>
   </div>
   <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
   <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Step</button></div>
  </form>
 </div></div>
</div>
<?php endif; ?>
