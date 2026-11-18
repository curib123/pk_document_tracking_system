<select name="type" hidden><option value="disposal">Disposal</option></select>
<div class="col-md-6">
  <label class="form-label" for="disposalRequestSubject">Subject / Reason</label>
  <textarea class="form-control" id="disposalRequestSubject" name="subject" rows="1" maxlength="255" required
    autocomplete="off"></textarea>
</div>
<div class="col-md-6" data-domain-picker>
  <label class="form-label" for="disposalDocumentDomain">Document Type</label>
  <select class="form-select" id="disposalDocumentDomain" name="document_domain" required autocomplete="off">
    <option value="hardcopy">Hardcopy</option>
    <option value="external">External Document</option>
  </select>
</div>
<div class="col-12" data-document-domain="hardcopy">
  <label class="form-label" for="disposalRequestHardcopy">Hardcopy Document</label>
  <select class="form-select" name="hardcopy_id" id="disposalRequestHardcopy" data-searchable
    autocomplete="off">
    <option value="">Choose an active document</option>
    <?php foreach ($disposal_hardcopy_options as $item): ?>
      <option value="<?= (int) $item['id'] ?>">
        <?= html_escape($item['title']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>
<div class="col-12" data-document-domain="external" hidden>
  <label class="form-label" for="disposalRequestExternal">External Document</label>
  <select class="form-select" name="external_document_id" id="disposalRequestExternal" data-request-external-source data-searchable disabled
    autocomplete="off">
    <option value="">Choose an active document</option>
    <?php foreach ($external_options as $item): ?>
      <option value="<?= (int) $item['id'] ?>"
        data-external-record="<?= html_escape(json_encode($item, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
        <?= html_escape($item['title'] . ' · Revision ' . ($item['revision_level'] ?? '00')) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>
<?php $this->load->view('pages/request/disposal_fields', ['disposal_field_prefix' => 'requestDisposal']); ?>
<div class="col-12">
  <label class="form-label" for="disposalRequestRemark">Remarks <span class="optional-label">(optional)</span></label>
  <textarea class="form-control" id="disposalRequestRemark" name="remarks" rows="3" autocomplete="off"></textarea>
  <p class="form-text mb-0">The document is disposed only after the approval workflow is completed.</p>
</div>
