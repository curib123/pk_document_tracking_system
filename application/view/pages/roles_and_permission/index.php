<?php
$canRole = function ($action) use ($permissions) { return isset($permissions['*']) || !empty($permissions['roles'][$action]); };
$dt_columns=['name'=>'Role','active'=>'Status','permissions'=>'Actions'];
$dt_rows=[];
foreach($rows as $r){
 $perms=$grants[$r['id']]??[];
 $dt_rows[]=['id'=>$r['id'],'cells'=>['name'=>$r['name'],
   'active'=>$r['active']?'active':'0','permissions'=>count($perms)],
  'record'=>['id'=>$r['id'],'name'=>$r['name'],'active'=>$r['active'],
    'permission_ids'=>$perms],
  'display'=>['Role'=>$r['name'],'Status'=>$r['active']?'Active':'Inactive',
    'Permission Count'=>count($perms)],
  'buttons'=>(!$canRole('edit') || strcasecmp($r['name'],'Administrator')===0)?[['type'=>'view']]:
    [['type'=>'view'],['type'=>'edit']]];
}
$dt_path='admin/roles';$dt_q=$table['q'];$dt_filter=$table['status'];
$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort=$table['sort'];$dt_dir=strtolower($table['dir']);$dt_sortable=['name','active'];
$dt_filters=['status'=>[''=>'All Statuses','1'=>'Active','0'=>'Inactive']];
$dt_filter_values=['status'=>$dt_filter];$dt_badges=['active'];
$dt_create=$canRole('add')?'Add Role':'';
?>
<div class="page-heading"><div><span class="eyebrow">Administration</span><h1>Roles & Permissions</h1>
<p>Module-action permissions from the original <code>permissions</code> table.</p></div></div>
<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir',
'dt_sortable','dt_filters','dt_filter_values','dt_badges','dt_create','dt_columns','dt_rows'
)); ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
<form id="editForm" method="post" action="<?= site_url('admin/roles/save') ?>" data-confirm="Save role access permissions?">
<div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">Role</h2>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body">
<input type="hidden" name="id" value="">
<div class="row g-3 mb-3"><div class="col-md-8">
<label class="form-label" for="roleName">Role Name</label>
<input class="form-control" id="roleName" name="name" required maxlength="120"></div>
<div class="col-md-4 d-flex align-items-end"><label class="form-check">
<input class="form-check-input" type="checkbox" name="active" value="1" checked> Active</label></div>
</div><h3 class="fs-6 fw-semibold">Module Permissions</h3>
<div class="row g-3">
<?php $groups=[];foreach($permission_rows as $p) $groups[$p['module_key']]['label']=$p['module_label'];
foreach($permission_rows as $p) $groups[$p['module_key']]['items'][]=$p;
foreach($groups as $key=>$group): ?>
<div class="col-md-4"><div class="border rounded-3 p-3 h-100">
<strong class="d-block mb-2"><?= html_escape($group['label']) ?></strong>
<?php foreach($group['items'] as $permission): ?>
<label class="permission-item d-block mb-1"><input class="form-check-input me-1"
 name="permissions[]" type="checkbox" value="<?= (int)$permission['id'] ?>">
<?= html_escape($permission['action_label']) ?></label>
<?php endforeach; ?>
</div></div><?php endforeach; ?></div></div>
<input type="hidden" name="confirmed" value="no">
<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
<div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
<button class="btn btn-primary" type="submit">Save Role</button></div></form>
</div></div></div>
