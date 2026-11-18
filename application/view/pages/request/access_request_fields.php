<select name="type" hidden>
  <?php foreach ($formTypes as $type): ?>
    <option value="<?= html_escape($type) ?>"><?= html_escape($actionLabels[$type]) ?></option>
  <?php endforeach; ?>
</select>
<div class="col-md-6"><label class="form-label" for="accessRequestSubject">Subject</label>
  <input class="form-control" name="subject" id="accessRequestSubject" maxlength="255" required autocomplete="off">
</div>
<div class="col-md-6" data-domain-picker>
  <label class="form-label" for="requestDocumentDomain">Document Type</label>
  <select class="form-select" id="requestDocumentDomain" name="document_domain" required autocomplete="off">
    <option value="softcopy">Softcopy</option>
    <option value="hardcopy">Hardcopy</option>
  </select>
</div>
<div class="col-md-6" data-document-domain="softcopy"><label class="form-label" for="accessRequestSoftcopy">Softcopy
    Document</label>
  <select class="form-select" name="softcopy_id" id="accessRequestSoftcopy" data-searchable autocomplete="off">
    <option value="">Not selected</option>
    <?php foreach ($catalog_softcopy_options as $item): ?>
      <option value="<?= (int) $item['id'] ?>">
        <?= html_escape($item['document_number'] . ' · ' . $item['title']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>
<div class="col-md-6" data-document-domain="hardcopy"><label class="form-label" for="accessRequestHardcopy">Hardcopy
    Document</label>
  <select class="form-select" name="hardcopy_id" id="accessRequestHardcopy" data-searchable autocomplete="off">
    <option value="">Not selected</option>
    <?php foreach ($catalog_hardcopy_options as $item): ?>
      <option value="<?= (int) $item['id'] ?>"><?= html_escape($item['title']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="col-md-6"><label class="form-label" for="accessRequestTitle">Proposed Document Title</label>
  <input class="form-control" name="title" id="accessRequestTitle" maxlength="255" autocomplete="off">
</div>
<div class="col-md-6"><label class="form-label" for="accessRequestNumber">Document Number</label>
  <input class="form-control" name="document_number" id="accessRequestNumber" maxlength="100" autocomplete="off">
</div>
<div class="col-md-6"><label class="form-label" for="accessRequestCategory">Category</label>
  <select class="form-select" name="category_id" id="accessRequestCategory" data-searchable autocomplete="off">
    <option value="">No category</option><?php foreach ($category_options as $opt): ?>
      <option value="<?= (int) $opt['id'] ?>"><?= html_escape($opt['name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="col-md-6"><label class="form-label" for="accessRequestUser">Recipient / Assigned User</label>
  <input class="form-control" id="accessRequestUser" type="text" value="<?= html_escape($user['name']) ?>" readonly
    autocomplete="off">
  <input type="hidden" name="recipient_id" value="<?= (int) $user['id'] ?>">
</div>
<div class="col-md-6"><label class="form-label" for="accessRequestExpires">Access Expires At</label>
  <input class="form-control" name="expires_at" type="date" id="accessRequestExpires" autocomplete="off">
</div>
<div class="col-md-6"><label class="form-label" for="accessRequestDest">Destination Location</label>
  <select class="form-select" name="destination_location_id" id="accessRequestDest" data-searchable
    autocomplete="off">
    <option value="">Not selected</option><?php foreach ($location_options as $opt): ?>
      <option value="<?= (int) $opt['id'] ?>"><?= html_escape($opt['name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div class="col-12"><label class="form-label" for="accessRequestRemark">Remarks <span
      class="optional-label">(optional)</span></label>
  <textarea class="form-control" id="accessRequestRemark" name="remarks" rows="3" autocomplete="off"></textarea>
</div>
