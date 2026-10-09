<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="page-heading">
 <div><span class="eyebrow">Administration</span><h1>Assign Documents</h1>
 <p>Assign an existing Softcopy to a staff account or set a Hardcopy's current holder.</p></div>
</div>
<div class="workspace-card p-4 mb-4">
 <h2 class="fs-6 fw-bold mb-3">New Administrative Assignment</h2>
 <form id="assignmentForm" method="post" action="<?= site_url('admin/document-assignments/save') ?>"
       data-confirm="Assign this document to the selected user?">
 <div class="row g-3">
  <div class="col-md-4">
   <label class="form-label" for="assignmentDomain">Document Type</label>
   <select class="form-select" name="document_domain" id="assignmentDomain" required>
    <option value="softcopy">Softcopy</option><option value="hardcopy">Hardcopy</option>
   </select>
  </div>
  <div class="col-md-4" data-document-domain="softcopy">
   <label class="form-label" for="assignmentSoftcopy">Softcopy Document</label>
   <select class="form-select" id="assignmentSoftcopy" name="softcopy_id" data-searchable required>
    <option value="">Choose a softcopy</option>
    <?php foreach ($softcopy_options as $doc): ?>
    <option value="<?= (int)$doc['id'] ?>"><?= html_escape($doc['document_number'].' · '.$doc['title']) ?></option>
    <?php endforeach; ?>
   </select>
  </div>
  <div class="col-md-4" data-document-domain="hardcopy" hidden>
   <label class="form-label" for="assignmentHardcopy">Hardcopy Document</label>
   <select class="form-select" id="assignmentHardcopy" name="hardcopy_id" data-searchable disabled>
    <option value="">Choose a hardcopy</option>
    <?php foreach ($hardcopy_options as $doc): ?>
    <option value="<?= (int)$doc['id'] ?>"><?= html_escape($doc['title']) ?></option>
    <?php endforeach; ?>
   </select>
  </div>
  <div class="col-md-4">
   <label class="form-label" for="assignmentUser">Assigned User</label>
   <select class="form-select" id="assignmentUser" name="recipient_id" data-searchable required>
    <option value="">Choose a user</option>
    <?php foreach ($users_list as $option): ?>
    <option value="<?= (int)$option['id'] ?>"><?= html_escape($option['name']) ?></option>
    <?php endforeach; ?>
   </select>
  </div>
  <div class="col-12">
   <input type="hidden" name="confirmed" value="no">
   <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
          value="<?= $this->security->get_csrf_hash() ?>">
   <button class="btn btn-primary" type="submit">
    <i class="fa-solid fa-user-check me-1"></i> Assign Document
   </button>
  </div>
 </div></form>
</div>
<div class="workspace-card">
 <div class="workspace-card-header">
  <strong>Current Document Assignments</strong>
  <p class="small text-secondary mb-0">Recent softcopy assignments and hardcopy custody.</p>
 </div>
 <div class="table-responsive"><table class="table align-middle">
  <thead><tr><th>Type</th><th>Document</th><th>Reference</th>
    <th>Assigned To</th><th>Updated</th><th>Action</th></tr></thead>
  <tbody>
   <?php if (!$assignment_rows): ?><tr><td colspan="6">No document assignments yet.</td></tr><?php endif; ?>
   <?php foreach ($assignment_rows as $item): ?>
   <tr><td><?= html_escape(ucfirst($item['domain'])) ?></td>
    <td><?= html_escape($item['title']) ?></td>
    <td><?= html_escape($item['code']?:'—') ?></td>
    <td><?= html_escape($item['assignee']) ?></td>
    <td><?= html_escape($item['assigned_date']) ?></td>
<td>
 <button type="button" class="btn btn-light btn-sm js-edit"
   data-target="#assignmentForm"
   data-record="<?= html_escape(json_encode([
      'document_domain'=>$item['domain'],
      'softcopy_id'=>$item['domain']==='softcopy'?$item['document_id']:'',
      'hardcopy_id'=>$item['domain']==='hardcopy'?$item['document_id']:'',
      'recipient_id'=>$item['recipient_id']
   ])) ?>" data-title="Change Assignment">
   <i class="fa-solid fa-pen me-1"></i> Change
 </button>
</td></tr>
   <?php endforeach; ?>
  </tbody>
 </table></div>
</div>
