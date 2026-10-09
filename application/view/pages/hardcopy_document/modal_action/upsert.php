<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Native CodeIgniter modal: all choices come from the existing pk_dts tables.
$isAdmin = !empty($is_administrator);
$myHolderId = (int) ($user['id'] ?? 0);
$myHolderName = trim((string) ($user['name'] ?? ''));

?>
<div class="col-12">
  <h3 class="fs-6 fw-bold mb-0">Document details</h3>
  <p class="small text-secondary mb-0">Create or update the physical document's metadata and location.</p>
</div>
<div class="col-md-8">
  <label class="form-label" for="hardcopyTitle">Document Title</label>
  <input class="form-control" id="hardcopyTitle" name="title" required maxlength="255"
    placeholder="Enter the hardcopy document title">
</div>
<div class="col-md-4">
  <label class="form-label" for="hardcopySequence">Sequence / Copy Number <span class="optional-label">(optional)</span></label>
  <input class="form-control" id="hardcopySequence" name="sequence_number" maxlength="100"
    placeholder="e.g. PK-REC-01">
</div>
<div class="col-12 mt-3">
  <h3 class="fs-6 fw-bold mb-0">Predefined physical location</h3>
  <p class="small text-secondary mb-0">Choose existing records from Area, Specific, Asset and Location. Selecting a child automatically fills its parent hierarchy.</p>
</div>
<div class="col-md-6">
  <label class="form-label" for="hardcopyArea">Area <span class="optional-label">(optional)</span></label>
  <select class="form-select" id="hardcopyArea" name="area_id"
    data-hardcopy-level="area" data-searchable>
    <option value="">No area selected</option>
    <?php foreach ($options['areas'] as $area): ?>
      <option value="<?= (int)$area['id'] ?>"><?= html_escape($area['name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="col-md-6">
  <label class="form-label" for="hardcopySpecific">Specific <span class="optional-label">(optional)</span></label>
  <select class="form-select" id="hardcopySpecific" name="specific_id"
    data-hardcopy-level="specific" data-searchable>
    <option value="">No specific selected</option>
    <?php foreach ($options['specifics'] as $specific): ?>
      <option value="<?= (int)$specific['id'] ?>"
        data-area-id="<?= (int)$specific['area_id'] ?>"><?= html_escape($specific['name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="col-md-6">
  <label class="form-label" for="hardcopyAsset">Asset <span class="optional-label">(optional)</span></label>
  <select class="form-select" id="hardcopyAsset" name="asset_id"
    data-hardcopy-level="asset" data-searchable>
    <option value="">No asset selected</option>
    <?php foreach ($options['assets'] as $asset): ?>
      <option value="<?= (int)$asset['id'] ?>"
        data-specific-id="<?= (int)$asset['specific_id'] ?>"
        data-area-id="<?= (int)$asset['area_id'] ?>"><?= html_escape($asset['name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="col-md-6">
  <label class="form-label" for="hardcopyLocation">Location <span class="optional-label">(optional)</span></label>
  <select class="form-select" id="hardcopyLocation" name="location_id"
    data-hardcopy-level="location" data-searchable>
    <option value="">No location selected</option>
    <?php foreach ($options['locations'] as $location): ?>
      <option value="<?= (int)$location['id'] ?>"
        data-area-id="<?= (int)($location['area_id']??0) ?>"
        data-specific-id="<?= (int)($location['specific_id']??0) ?>"
        data-asset-id="<?= (int)($location['asset_id']??0) ?>"><?= html_escape($location['name'].' · '.$location['code']) ?></option>
    <?php endforeach; ?>
  </select>
  <small class="form-text">Only active, predefined locations are available.</small>
</div>
<div class="col-12">
  <p class="form-text mb-0" id="hardcopyHierarchyHelp" role="status">
    Choose any available location or filter locations by area, specific or asset.
  </p>
</div>
<div class="col-12 mt-3">
  <h3 class="fs-6 fw-bold mb-0">Document custody</h3>
</div>
<div class="col-md-6">
  <label class="form-label" for="hardcopyHolder">Current Holder</label>
  <?php if ($isAdmin): ?>
  <select class="form-select" id="hardcopyHolder" name="holder_id" required data-searchable>
    <option value="">Select holder</option>
    <?php foreach ($options['users'] as $holder): ?>
      <option value="<?= (int)$holder['id'] ?>"
        <?= (int)$holder['id']===$myHolderId?'selected':'' ?>><?= html_escape($holder['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <small class="form-text">Administrators may assign a different active holder.</small>
  <?php else: ?>
  <input class="form-control" id="hardcopyHolder" name="holder_name"
    value="<?= html_escape($myHolderName) ?>" readonly
    aria-describedby="hardcopyHolderHelp">
  <input type="hidden" name="holder_id" value="<?= $myHolderId ?>">
  <small class="form-text" id="hardcopyHolderHelp">Automatically assigned to your signed-in account. Only an Administrator may change this.</small>
  <?php endif; ?>
</div>
<div class="col-md-6">
  <label class="form-check mt-4">
    <input class="form-check-input" type="checkbox" id="hardcopyRetention"
      name="retention_enabled" value="1"> Track retention period
  </label>
</div>
<div class="col-md-6">
  <label class="form-label" for="hardcopyRetentionStart">Retention Start <span class="optional-label">(optional)</span></label>
  <input class="form-control" id="hardcopyRetentionStart" type="date" name="retention_start_date">
</div>
<div class="col-md-6">
  <label class="form-label" for="hardcopyRetentionEnd">Retention End <span class="optional-label">(optional)</span></label>
  <input class="form-control" id="hardcopyRetentionEnd" type="date" name="retention_end_date">
</div>
<div class="col-12">
  <label class="form-label" for="hardcopyCreationReason">Reason / Notes <span class="optional-label">(optional)</span></label>
  <textarea class="form-control" id="hardcopyCreationReason"
    name="creation_reason" rows="2" placeholder="Optional document notes"></textarea>
</div>
