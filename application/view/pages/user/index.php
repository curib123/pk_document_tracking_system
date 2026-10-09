<?php
$dt_columns=['name'=>'Full Name','username'=>'Username','position'=>'Position',
 'role'=>'Role','leader'=>'Leader','active'=>'Status'];
$dt_rows=[];
foreach($rows as $r){
 $name=$r['first_name'].' '.$r['last_name'];
 $dt_rows[]=['id'=>$r['id'],'cells'=>[
 'name'=>$name,'username'=>$r['username'],'position'=>$r['position_title'],
 'role'=>$r['role_name'],'leader'=>$r['leader_name'],'active'=>$r['active']?'active':'0'],
 'display'=>['Name'=>$name,'Username'=>$r['username'],'Position'=>$r['position_title'],
 'Role'=>$r['role_name'],'Leader'=>$r['leader_name'],'Status'=>$r['active']?'Active':'Inactive'],
 'record'=>['id'=>$r['id'],'username'=>$r['username'],'first_name'=>$r['first_name'],
 'middle_name'=>$r['middle_name'],'last_name'=>$r['last_name'],
 'position_title'=>$r['position_title'],'role_id'=>$r['role_id'],
 'leader_id'=>$r['leader_id'],'active'=>$r['active']],
 'buttons'=>[['type'=>'view'],['type'=>'edit']]];
 if ($r['active'] && $r['id']!=$user['id']) $dt_rows[count($dt_rows)-1]['buttons'][]=
  ['type'=>'action','url'=>'admin/users/deactivate','label'=>'Deactivate',
  'description'=>'Disable this user while preserving related history.','icon'=>'fa-solid fa-user-slash'];
}
$dt_path='admin/users';$dt_q=$table['q'];$dt_filter=$table['status'];
$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort='username';$dt_dir='asc';$dt_sortable=[];
$dt_filters=['status'=>[''=>'All Statuses','1'=>'Active','0'=>'Inactive']];
$dt_filter_values=['status'=>$dt_filter];$dt_badges=['active'];
$dt_create='Add User';
?>
<div class="page-heading"><div><span class="eyebrow">Administration</span><h1>User Management</h1>
<p>Manage staff accounts and reporting lines with server-enforced permissions.</p></div></div>
<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir',
'dt_sortable','dt_filters','dt_filter_values','dt_badges','dt_create','dt_columns','dt_rows'
)); ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
<form id="editForm" method="post" action="<?= site_url('admin/users/save') ?>" data-confirm="Save user account?">
<div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">User</h2>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><div class="row g-3">
<input type="hidden" name="id" value="">
<?php foreach(['first_name'=>'First Name','middle_name'=>'Middle Name','last_name'=>'Last Name',
 'username'=>'Username','position_title'=>'Position Title'] as $field=>$label): ?>
<div class="col-md-6"><label class="form-label" for="u-<?= $field ?>"><?= $label ?></label>
<input class="form-control" id="u-<?= $field ?>" name="<?= $field ?>" maxlength="150"
<?= $field==='middle_name'?'':'required' ?>></div>
<?php endforeach; ?>
<div class="col-md-6"><label class="form-label" for="u-role">Role</label>
<select class="form-select" name="role_id" id="u-role" required>
<option value="">Select role</option>
<?php foreach($roles as $r): ?><option value="<?= (int)$r['id'] ?>"><?= html_escape($r['name']) ?></option>
<?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="u-leader">Leader <span class="optional-label">(optional)</span></label>
<select class="form-select" name="leader_id" id="u-leader" data-searchable>
<option value="">No leader</option>
<?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= html_escape($u['name']) ?></option>
<?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="u-password">Initial / New Password</label>
<input class="form-control" id="u-password" name="password" type="password" minlength="12"
autocomplete="new-password"><div class="form-text">Minimum 12 characters. Leave empty when editing to keep password.</div></div>
<div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox"
name="active" value="1" checked> Active account</label></div>
</div></div>
<input type="hidden" name="confirmed" value="no">
<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
<div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
<button class="btn btn-primary" type="submit">Save User</button></div>
</form></div></div></div>
