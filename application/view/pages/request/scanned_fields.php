<div class="col-12"><p class="text-secondary mb-0">Every upload, update, deletion and new category requires a reason and final DCO approval. Save a draft, then submit it. Approved records stay unchanged while a request is pending.</p></div>
<div class="col-md-6">
  <label class="form-label" for="reqType">Request Action</label>
  <select class="form-select" name="type" id="reqType" required>
    <?php foreach (['scanned_create' => 'Upload Scanned Document', 'scanned_update' => 'Update Scanned Document', 'scanned_delete' => 'Delete Scanned Document', 'scanned_category_create' => 'Create Category / Folder'] as $value => $label): ?>
      <?php if (in_array($value, $allowed_request_types, TRUE)): ?><option value="<?= $value ?>"><?= $label ?></option><?php endif; ?>
    <?php endforeach; ?>
  </select>
</div>
<div class="col-md-6"><label class="form-label" for="reqSubject">Subject</label><input class="form-control" id="reqSubject" name="subject" maxlength="255" required></div>
<div class="col-12" data-scanned-for="scanned_update scanned_delete">
  <label class="form-label" for="scanDocument">Existing Scanned Document</label>
  <select class="form-select" id="scanDocument" name="scanned_document_id" data-scanned-required>
    <option value="">Choose a document</option>
    <?php foreach ($scanned_options as $doc): ?>
      <option value="<?= (int) $doc['id'] ?>" data-scanned-record="<?= html_escape(json_encode($doc)) ?>"><?= html_escape($doc['title']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="hidden" name="document_version" value="">
</div>
<div class="col-12" data-scanned-for="scanned_create scanned_update scanned_category_create">
  <label class="form-label" for="scanTitle">Document Title / Category Name</label>
  <input class="form-control" id="scanTitle" name="title" maxlength="255" data-scanned-required>
</div>
<div class="col-12" data-scanned-for="scanned_category_create">
  <label class="form-label" for="scanParent">Approved Parent Category</label>
  <select class="form-select" id="scanParent" name="parent_id">
    <option value="">No parent — create a root folder</option>
    <?php foreach ($scanned_categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= html_escape($category['path']) ?></option><?php endforeach; ?>
  </select>
  <small class="form-text">Root and nested folders both need DCO approval before use.</small>
</div>
<div class="col-12" data-scanned-for="scanned_create scanned_update">
  <label class="form-label" for="scanCategory">Approved Category</label>
  <select class="form-select" id="scanCategory" name="scanned_category_id" data-scanned-required>
    <option value="">Choose an approved folder</option>
    <?php foreach ($scanned_categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= html_escape($category['path']) ?></option><?php endforeach; ?>
  </select>
  <?php if (!$scanned_categories): ?><small class="form-text">Request a category first and wait for DCO approval.</small><?php endif; ?>
</div>
<div class="col-md-6" data-scanned-for="scanned_create scanned_update"><label class="form-label" for="scanRetentionStart">Retention Start Date</label><input type="date" class="form-control" id="scanRetentionStart" name="retention_start_date" data-scanned-required></div>
<div class="col-md-6" data-scanned-for="scanned_create scanned_update"><label class="form-label" for="scanRetentionEnd">Retention End Date</label><input type="date" class="form-control" id="scanRetentionEnd" name="retention_end_date" data-scanned-required><small class="form-text">Includes the full end date in Philippine time.</small></div>
<div class="col-12" data-scanned-for="scanned_create scanned_update">
  <label class="form-label" for="scanFiles">Files / Scanned Documents</label>
  <input class="form-control" id="scanFiles" type="file" name="scanned_attachment[]" multiple accept=".pdf,.png,.jpg,.jpeg,.txt,.docx,.xlsx">
  <small class="form-text">Up to 20 files, 15 MB each: PDF, PNG, JPEG, TXT, DOCX or XLSX. For updates, leave blank to keep current files. Choosing files replaces the current set after approval.</small>
</div>
<div class="col-12"><label class="form-label" for="scanReason">Remarks / Reason (required)</label><textarea class="form-control" id="scanReason" name="remarks" rows="3" maxlength="2000" required></textarea></div>
