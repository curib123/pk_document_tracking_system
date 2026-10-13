<?php
$canRole = function ($action) use ($permissions) { return isset($permissions['*']) || !empty($permissions['roles'][$action]); };
$dt_columns=['name'=>'Role','active'=>'Status','permissions'=>'Actions'];
$dt_rows=[];
foreach($rows as $r){
 $perms=$grants[$r['id']]??[];
 $reserved=strcasecmp($r['name'],'Administrator')===0;
 $unused=empty($r['user_count']) && empty($r['workflow_count']) && empty($r['request_count']);
 $buttons=[['type'=>'view']];
 if (!$reserved && $canRole('edit')) $buttons[]=['type'=>'edit'];
 if (!$reserved && $unused && $r['active'] && $canRole('edit')) $buttons[]=[
   'type'=>'action','label'=>'Deactivate','icon'=>'fa-solid fa-ban','url'=>'admin/roles/deactivate',
   'description'=>'Deactivate the unused role "'.$r['name'].'"?'];
 if (!$reserved && $unused && $canRole('delete')) $buttons[]=[
   'type'=>'action','label'=>'Remove','icon'=>'fa-solid fa-trash-can','url'=>'admin/roles/remove',
   'description'=>'Permanently remove the unused role "'.$r['name'].'" and its permission assignments?'];
 $dt_rows[]=['id'=>$r['id'],'cells'=>['name'=>$r['name'],
   'active'=>$r['active']?'active':'0','permissions'=>count($perms)],
  'record'=>['id'=>$r['id'],'name'=>$r['name'],'active'=>$r['active'],
    'permission_ids'=>$perms],
  'display'=>['Role'=>$r['name'],'Status'=>$r['active']?'Active':'Inactive',
    'Permission Count'=>count($perms)],
  'buttons'=>$buttons];
}
$dt_path='admin/roles';$dt_q=$table['q'];$dt_filter=$table['status'];
$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort=$table['sort'];$dt_dir=strtolower($table['dir']);$dt_sortable=['name','active'];
$dt_filters=['status'=>[''=>'All Statuses','1'=>'Active','0'=>'Inactive']];
$dt_filter_values=['status'=>$dt_filter];$dt_badges=['active'];
$dt_create=$canRole('add')?'Add Role':'';
?>

<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir',
'dt_sortable','dt_filters','dt_filter_values','dt_badges','dt_create','dt_columns','dt_rows'
)); ?>
<div class="modal fade role-permissions-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
<form id="editForm" method="post" action="<?= site_url('admin/roles/save') ?>" data-confirm="Save role access permissions?">
<div class="modal-header"><div class="role-permissions-heading"><span class="role-permissions-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
<div><p class="role-permissions-eyebrow">ACCESS MANAGEMENT</p><h2 class="modal-title fs-6" id="editTitle">Role</h2></div></div>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body">
<input type="hidden" name="id" value="">
<div class="role-basics row g-3"><div class="col-md-8">
<label class="form-label" for="roleName">Role Name</label>
<input class="form-control" id="roleName" name="name" required maxlength="120"></div>
<div class="col-md-4 role-status-field"><span class="form-label">Status</span><label class="permission-pill role-status-toggle">
<input class="permission-pill-control" type="checkbox" name="active" value="1" checked>
<span class="permission-pill-surface"><span class="permission-pill-mark" aria-hidden="true"></span><span>Active role</span></span></label></div>
</div><div class="permission-section-heading"><h3 class="fs-6 fw-semibold mb-1">Module Permissions</h3>
<p class="mb-0">Select the actions this role can use.</p></div>
<div class="row g-3 permission-groups">
<?php $groups=[];foreach($permission_rows as $p) $groups[$p['module_key']]['label']=$p['module_label'];
foreach($permission_rows as $p) $groups[$p['module_key']]['items'][]=$p;
foreach($groups as $key=>$group): ?>
<div class="col-md-6 col-xl-4"><fieldset class="permission-group">
<legend><?= html_escape($group['label']) ?></legend><div class="permission-pills">
<?php foreach($group['items'] as $permission): ?>
<label class="permission-pill"><input class="permission-pill-control"
 name="permissions[]" type="checkbox" value="<?= (int)$permission['id'] ?>">
<span class="permission-pill-surface"><span class="permission-pill-mark" aria-hidden="true"></span>
<span><?= html_escape($permission['action_label']) ?></span></span></label>
<?php endforeach; ?>
</div></fieldset></div><?php endforeach; ?></div></div>
<input type="hidden" name="confirmed" value="no">
<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
<div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
<button class="btn btn-primary" type="submit">Save Role</button></div></form>
</div></div></div>
