<?php
$submitFromModal = $canRequest('submit');
$fieldViews = [
  'softcopy' => 'softcopy_fields',
  'external' => 'external_fields',
  'scanned' => 'scanned_fields',
  'hardcopy-transfer' => 'hardcopy_transfer_fields',
  'hardcopy-disposal' => 'disposal_request_fields',
  'access-grant' => 'access_request_fields'
];
$formGroups = [];
foreach ($allowed_request_types as $type) {
  $category = $type_tabs[$type];
  $formGroup = $category === 'document-assign' ? 'access-grant' : $category;
  $formGroups[$formGroup][] = $type;
}
?>
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editModal" tabindex="-1"
  aria-labelledby="editTitle" aria-hidden="true"
  <?php if (!empty($revision_template_document_id)): ?>
    data-revision-template-document="<?= (int) $revision_template_document_id ?>"
  <?php endif; ?>>
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-6" id="editTitle">New Request</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="requestActionPicker">Request Action</label>
          <select class="form-select" id="requestActionPicker" autocomplete="off">
            <option value="">Choose a request action</option>
            <?php foreach ($categories as $category => $label): ?>
              <?php $options = array_intersect($allowed_request_types, array_keys($type_tabs, $category, TRUE)); ?>
              <?php if (!$options) continue; ?>
              <optgroup label="<?= html_escape($label) ?>">
                <?php foreach ($options as $type): ?>
                  <option value="<?= html_escape($type) ?>"
                    data-request-form="request-form-<?= $category === 'document-assign' ? 'access-grant' : $category ?>"
                    data-request-draft-url="<?= site_url('my-requests/' . $category . '/save') ?>"
                    <?php if ($submitFromModal): ?>data-request-submit-url="<?= site_url('my-requests/' . $category . '/save-and-submit') ?>"<?php endif; ?>>
                    <?= html_escape($actionLabels[$type]) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
        <?php foreach ($formGroups as $group => $formTypes): ?>
          <form id="request-form-<?= $group ?>" method="post" enctype="multipart/form-data" hidden
            data-request-form data-allowed-request-types="<?= html_escape(json_encode($formTypes)) ?>"
            <?= $group === 'scanned' ? 'data-scanned-form' : ($group === 'hardcopy-transfer' ? 'data-hardcopy-transfer' : '') ?>
            data-confirm="Save request draft?" autocomplete="off">
            <div class="row g-3">
              <?php
              $fieldData = match ($group) {
                'softcopy' => ['category_options' => $numbered_category_options] +
                  compact('softcopy_options', 'numbering_options', 'number_previews'),
                'scanned' => compact('allowed_request_types', 'scanned_options', 'scanned_categories'),
                'external' => compact('external_options', 'external_category_options', 'subsidiary_options'),
                'hardcopy-transfer' => compact('transfer_sources', 'transfer_options'),
                'hardcopy-disposal' => compact('disposal_hardcopy_options', 'external_options'),
                'access-grant' => compact('formTypes', 'actionLabels', 'catalog_softcopy_options',
                  'catalog_hardcopy_options', 'category_options', 'location_options', 'user')
              };
              $this->load->view('pages/request/' . $fieldViews[$group], $fieldData);
              ?>
            </div>
            <input type="hidden" name="id" value="">
            <input type="hidden" name="version" value="">
            <input type="hidden" name="return_query" value="">
            <input type="hidden" name="confirmed" value="no">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
              value="<?= $this->security->get_csrf_hash() ?>">
          </form>
        <?php endforeach; ?>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn <?= $submitFromModal ? 'btn-outline-primary' : 'btn-primary' ?>" id="requestFormSaveDraft"
          type="submit" disabled>Save Draft</button>
        <?php if ($submitFromModal): ?>
          <button class="btn btn-primary" id="requestFormSubmit" type="submit"
            data-confirm="Submit this request for approval?" disabled>Submit Request</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
