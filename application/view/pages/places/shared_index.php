<?php
// Shared Places screen: each master-branch module keeps its own index.php.
$permissions = $permissions ?? [];
$can = function ($action) use ($permissions,$cfg) {
    if (!empty($cfg['read_only']) && $action !== 'view') return FALSE;
    return isset($permissions['*']) || !empty($permissions[$cfg['table']][$action]);
};
$dt_columns = [];
$labels = ['name'=>'Name','asset_number'=>'Asset Number','sequence_key'=>'Sequence',
    'value'=>'Current Value','code'=>'Location Code','description'=>'Description','folder_name'=>'Folder',
    'area_name'=>'Area','specific_name'=>'Specific','asset_name'=>'Asset','parent_name'=>'Parent'];
foreach ($cfg['fields'] as $field) {
    if (str_ends_with($field,'_id')) {
        $key = str_replace('_id','_name',$field);
        if ($field === 'parent_id') $key='parent_name';
        if (isset($labels[$key])) $dt_columns[$key]=$labels[$key];
    } else {
        $dt_columns[$field]=$labels[$field] ?? ucwords(str_replace('_',' ',$field));
    }
}
if ($cfg['active']) $dt_columns['active']='Status';
$dt_rows=[];
foreach ($rows as $row) {
    $cells=[];
    foreach ($dt_columns as $field=>$label) {
        $value=$row[$field] ?? '';
        $cells[$field]=$field==='active' ? ($value?'active':'0') : $value;
    }
    $display=[];
    foreach ($dt_columns as $field=>$label) $display[$label]=$cells[$field];
    $record=[];
    foreach (array_merge(['id'],$cfg['fields'],['active']) as $field) {
        if (array_key_exists($field,$row)) $record[$field]=$row[$field];
    }
    $buttons=[['type'=>'view']];
    if ($can('edit')) $buttons[]=['type'=>'edit'];
    if ($cfg['active'] && $can('delete') && !empty($row['active'])) {
        $buttons[]=['type'=>'action','url'=>'places/'.$cfg['slug'].'/deactivate',
            'label'=>'Deactivate','description'=>'Deactivate this reference item without deleting historical links.',
            'icon'=>'fa-solid fa-ban'];
    }
    $dt_rows[]=['id'=>$row['id'] ?? 0,'cells'=>$cells,'record'=>$record,
        'display'=>$display,'buttons'=>$buttons];
}
$dt_path='places/'.$cfg['slug'];
$dt_q=$table['q'];$dt_filter=$table['status'];$dt_page=$table['page'];
$dt_limit=$table['limit'];$dt_total=$total;
$dt_filters=$cfg['active']?['status'=>[''=>'All Statuses','1'=>'Active','0'=>'Inactive']]:[];
$dt_filter_values=['status'=>$dt_filter];
$dt_badges=['active'];
$dt_create=$can('add')?'Add '.$cfg['label']:'';
$dt_sort=$cfg['key'];$dt_dir='asc';
$dt_sortable=[];
?>
<div class="page-heading"><div><span class="eyebrow">Master Data</span>
<h1><?= html_escape($title) ?></h1>
<p>Reference data stored in the original <code><?= html_escape($cfg['table']) ?></code> table.<?php if (!empty($cfg['read_only'])): ?> System counters are read-only.<?php endif; ?></p>
</div></div>
<nav class="tab-bar" aria-label="Place categories">
<?php foreach ([
 'area'=>'Area','specific'=>'Specific','asset'=>'Asset','location'=>'Location',
 'sequence'=>'Sequence','softcopy-categories'=>'Softcopy Categories'
] as $slug=>$label):
 $perm=['area'=>'areas','specific'=>'specifics','asset'=>'assets','location'=>'locations',
    'sequence'=>'sequences','softcopy-categories'=>'categories'][$slug];
 if (isset($permissions['*']) || !empty($permissions[$perm]['view'])): ?>
<a href="<?= site_url('places/'.$slug) ?>" class="tab-link <?= $cfg['slug']===$slug?'active':'' ?>">
<?= html_escape($label) ?></a>
<?php endif; endforeach; ?>
</nav>
<?php $this->load->view('reusable_components/reusable_datatable',compact(
'dt_path','dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_filters','dt_filter_values',
'dt_badges','dt_rows','dt_columns','dt_create','dt_sort','dt_dir','dt_sortable'
)); ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
 <form id="editForm" method="post" action="<?= site_url('places/'.$cfg['slug'].'/save') ?>" data-confirm="Save these changes?">
  <div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">Add Place</h2>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><div class="row g-3">
   <input type="hidden" name="id" value="">
   <?php foreach ($cfg['fields'] as $field):
    $references=['area_id'=>'areas','specific_id'=>'specifics','asset_id'=>'assets','parent_id'=>'categories'];
    if (isset($references[$field])):
      $optName=$references[$field]; ?>
    <div class="col-md-6">
     <label class="form-label" for="f-<?= html_escape($field) ?>"><?= html_escape(ucwords(str_replace('_',' ',$field))) ?></label>
     <select class="form-select" id="f-<?= html_escape($field) ?>" name="<?= html_escape($field) ?>" data-searchable>
      <option value="">Select <?= html_escape(str_replace('_id','',$field)) ?></option>
      <?php foreach ($options[$optName] as $option): ?>
       <option value="<?= (int)$option['id'] ?>"><?= html_escape($option['name']) ?></option>
      <?php endforeach; ?>
     </select>
    </div>
    <?php else: ?>
     <div class="col-md-6">
     <label class="form-label" for="f-<?= html_escape($field) ?>"><?= html_escape($labels[$field] ?? ucwords(str_replace('_',' ',$field))) ?></label>
     <?php if ($field==='description'): ?>
     <textarea class="form-control" id="f-<?= html_escape($field) ?>" name="<?= html_escape($field) ?>" rows="2"></textarea>
     <?php else: ?>
     <input class="form-control" id="f-<?= html_escape($field) ?>" name="<?= html_escape($field) ?>"
      <?= $field===$cfg['key'] || $field==='folder_name' || $field==='code'?'required':'' ?>
      <?= $field==='value'?'type="number" min="0"':'maxlength="150"' ?>>
     <?php endif; ?></div>
    <?php endif; endforeach; ?>
   <?php if ($cfg['active']): ?>
    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" checked> Active</label></div>
   <?php endif; ?>
  </div></div>
  <input type="hidden" name="confirmed" value="no">
  <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
  <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-primary" type="submit">Save Record</button></div>
 </form></div></div></div>
