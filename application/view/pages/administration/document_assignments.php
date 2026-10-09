<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$domain=$selected_domain;
$folderQuery=['domain'=>$domain];
if ($folder_value!=='') $folderQuery['folder']=$folder_value;
?>
<div class="page-heading">
 <div><span class="eyebrow">Administration</span><h1>Assign Documents</h1>
 <p>Browse by physical location or category folder, then assign a document to a staff account.</p></div>
</div>
<nav class="tab-bar" aria-label="Assignment document type">
 <?php foreach (['hardcopy'=>'Hardcopy / Places','softcopy'=>'Softcopy / Categories'] as $key=>$label): ?>
 <a class="tab-link <?= $domain===$key?'active':'' ?>"
    href="<?= site_url('admin/document-assignments').'?domain='.$key ?>"
    <?= $domain===$key?'aria-current="page"':'' ?>>
    <i class="fa-solid <?= $key==='hardcopy'?'fa-box-archive':'fa-folder-tree' ?> me-1" aria-hidden="true"></i>
    <?= html_escape($label) ?>
 </a>
 <?php endforeach; ?>
</nav>
<?php $this->load->view('reusable_components/folder_browser',[
    'folder_browser'=>$folder_browser,'folder_base'=>$folder_base,
    'folder_params'=>$folder_params,'domain'=>$domain
]); ?>

<div class="workspace-card">
 <div class="workspace-card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
  <div><strong><?= html_escape(ucfirst($domain)) ?> Assignments</strong>
   <p class="text-secondary small mb-0">Showing the current folder's document assignments and holders.</p>
  </div>
  <button type="button" class="btn btn-primary js-edit"
    data-bs-toggle="modal" data-bs-target="#assignmentModal"
    data-target="#assignmentForm" data-record="{}"
    data-title="Assign Document">
    <i class="fa-solid fa-user-check me-1" aria-hidden="true"></i> Assign Document
  </button>
 </div>
</div>
<?php
$dt_path='admin/document-assignments';$dt_extra_params=['domain'=>$domain,'folder'=>$folder_value,'layout'=>$table['layout']];
$dt_columns=['title'=>'Document','code'=>'Reference','assignee'=>'Assigned To','assigned_date'=>'Updated'];
$dt_rows=[];
foreach ($assignment_rows as $item) {
    $details=['Document Type'=>ucfirst($domain),'Document'=>$item['title'],'Reference'=>$item['code']?:'—',
        'Assigned To'=>$item['assignee'],'Updated'=>$item['assigned_date']];
    $dt_rows[]=['id'=>$item['document_id'],'cells'=>$item,'display'=>$details,
        'record'=>['document_domain'=>$domain,'softcopy_id'=>$domain==='softcopy'?$item['document_id']:'',
            'hardcopy_id'=>$domain==='hardcopy'?$item['document_id']:'','recipient_id'=>$item['recipient_id']],
        'buttons'=>[['type'=>'view'],['type'=>'edit','modal'=>'#assignmentModal','form'=>'#assignmentForm']]];
}
$dt_q=$table['q'];$dt_filter='';$dt_page=$table['page'];$dt_limit=$table['limit'];$dt_total=$total;
$dt_sort=$table['sort'];$dt_dir=strtolower($table['dir']);$dt_sortable=array_keys($dt_columns);
$dt_filters=['owner'=>[''=>'All Assigned Users']+array_column($users_list,'name','id')];
$dt_filter_values=['owner'=>$table['owner']];$dt_badges=[];$dt_create='';
$this->load->view('reusable_components/reusable_datatable',compact('dt_path','dt_extra_params','dt_columns','dt_rows',
    'dt_q','dt_filter','dt_page','dt_limit','dt_total','dt_sort','dt_dir','dt_sortable','dt_filters','dt_filter_values','dt_badges','dt_create'));
?>

<div class="modal fade" id="assignmentModal" tabindex="-1"
     aria-labelledby="assignmentModalTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
  <form id="assignmentForm" method="post" action="<?= site_url('admin/document-assignments/save') ?>"
        data-confirm="Assign this document to the selected staff account?">
   <div class="modal-header">
    <h2 class="modal-title fs-6" id="assignmentModalTitle">Assign Document</h2>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
   </div>
   <div class="modal-body">
    <div class="row g-3">
     <input type="hidden" name="document_domain" value="<?= html_escape($domain) ?>">
     <input type="hidden" name="folder" value="<?= html_escape($folder_value) ?>">
     <?php if ($domain==='hardcopy'): ?>
      <div class="col-md-6" data-document-domain="hardcopy">
       <label class="form-label" for="assignmentHardcopy">Hardcopy Document</label>
       <select class="form-select" id="assignmentHardcopy" name="hardcopy_id" data-searchable required>
        <option value="">Choose hardcopy from this folder</option>
        <?php foreach ($hardcopy_options as $doc): ?>
        <option value="<?= (int)$doc['id'] ?>"><?= html_escape($doc['title']) ?></option>
        <?php endforeach; ?>
       </select>
      </div>
     <?php else: ?>
      <div class="col-md-6" data-document-domain="softcopy">
       <label class="form-label" for="assignmentSoftcopy">Softcopy Document</label>
       <select class="form-select" id="assignmentSoftcopy" name="softcopy_id" data-searchable required>
        <option value="">Choose softcopy from this folder</option>
        <?php foreach ($softcopy_options as $doc): ?>
        <option value="<?= (int)$doc['id'] ?>"><?= html_escape($doc['document_number'].' · '.$doc['title']) ?></option>
        <?php endforeach; ?>
       </select>
      </div>
     <?php endif; ?>
     <div class="col-md-6">
      <label class="form-label" for="assignmentUser">Assigned User</label>
      <select class="form-select" id="assignmentUser" name="recipient_id" data-searchable required>
       <option value="">Choose an active user</option>
       <?php foreach ($users_list as $person): ?>
       <option value="<?= (int)$person['id'] ?>"><?= html_escape($person['name']) ?></option>
       <?php endforeach; ?>
      </select>
     </div>
     <div class="col-12">
      <p class="form-text mb-2">Up to 500 matching choices. Use the page search or select a more specific folder to find other documents.</p>
      <p class="form-text mb-0">
       <?= $domain==='hardcopy'
         ? 'Hardcopy assignment updates the document holder. Physical transfers use the separate transfer workflow.'
         : 'Softcopy assignment grants access through the existing assignments table.' ?>
      </p>
     </div>
    </div>
   </div>
   <input type="hidden" name="confirmed" value="no">
   <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
          value="<?= $this->security->get_csrf_hash() ?>">
   <div class="modal-footer">
    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-primary" type="submit">Save Assignment</button>
   </div>
  </form>
 </div></div>
</div>
