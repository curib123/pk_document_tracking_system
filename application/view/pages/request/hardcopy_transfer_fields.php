<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<input type="hidden" name="type" value="transfer">
<div class="col-12">
 <h3 class="fs-6 fw-bold">Hardcopy Transfer</h3>
 <p class="text-secondary small">Original document details are read-only. Select the receiving user and a predefined destination.</p>
</div>
<div class="col-12">
 <label class="form-label" for="transferSource">Hardcopy Document</label>
 <select class="form-select" id="transferSource" name="hardcopy_id" data-transfer-source data-searchable required>
  <option value="">Select hardcopy document</option>
  <?php foreach($transfer_sources as $source): ?>
  <option value="<?= (int)$source['id'] ?>"
    data-transfer-doc="<?= html_escape(json_encode($source,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)) ?>">
    <?= html_escape($source['title'].($source['sequence_number']?' · '.$source['sequence_number']:'')) ?>
  </option>
  <?php endforeach; ?>
 </select>
</div>
<div class="col-12 mt-3">
 <h3 class="fs-6 fw-bold mb-1">Original Places and Holder</h3>
 <p class="small text-secondary">Automatically loaded from the selected hardcopy record.</p>
</div>
<?php foreach(['area_name'=>'Area','specific_name'=>'Specific','asset_name'=>'Asset',
 'location_name'=>'Location','location_code'=>'Location Code',
 'holder_name'=>'Current Holder'] as $key=>$label): ?>
<div class="col-md-6">
 <label class="form-label" for="original-<?= $key ?>"><?= html_escape($label) ?></label>
 <input class="form-control" id="original-<?= $key ?>" data-transfer-origin="<?= $key ?>"
   readonly placeholder="Select a hardcopy first">
</div>
<?php endforeach; ?>
<div class="col-12 mt-3">
 <h3 class="fs-6 fw-bold mb-1">Transfer Destination</h3>
 <p class="small text-secondary">Area → Specific → Asset → Location. Selecting a child fills its parents.</p>
</div>
<?php foreach([
 'area'=>['Area','areas'],'specific'=>['Specific','specifics'],
 'asset'=>['Asset','assets'],'location'=>['Location','locations']
] as $level=>$meta): ?>
<div class="col-md-6">
 <label class="form-label" for="transfer-<?= $level ?>">Destination <?= $meta[0] ?></label>
 <select class="form-select" id="transfer-<?= $level ?>" data-transfer-level="<?= $level ?>"
   name="destination_<?= $level ?>_id" data-searchable <?= $level==='location'?'required':'' ?>>
  <option value=""><?= $level==='location'?'Choose a location':'All '.$meta[0] ?></option>
  <?php foreach($transfer_options[$meta[1]] as $option): ?>
  <option value="<?= (int)$option['id'] ?>"
   <?php if($level!=='area'): ?>data-area-id="<?= (int)($option['area_id']??0) ?>"<?php endif; ?>
   <?php if($level==='asset'||$level==='location'): ?>data-specific-id="<?= (int)($option['specific_id']??0) ?>"<?php endif; ?>
   <?php if($level==='location'): ?>data-asset-id="<?= (int)($option['asset_id']??0) ?>"<?php endif; ?>>
   <?= html_escape($option['name'].($level==='location'?' · '.$option['code']:'')) ?>
  </option>
  <?php endforeach; ?>
 </select>
</div>
<?php endforeach; ?>
<div class="col-md-6">
 <label class="form-label" for="transferRecipient">Receiving User</label>
 <select class="form-select" id="transferRecipient" name="recipient_id" data-searchable required>
  <option value="">Select receiving user</option>
  <?php foreach($transfer_options['users'] as $option): ?>
    <option value="<?= (int)$option['id'] ?>"><?= html_escape($option['name']) ?></option>
  <?php endforeach; ?>
 </select>
</div>
<div class="col-md-6">
 <label class="form-label" for="transferSubject">Request Subject</label>
 <input class="form-control" id="transferSubject" name="subject" required maxlength="255">
</div>
<div class="col-12">
 <p class="small text-secondary mb-0" id="transferHelp" role="status">
   Destination must differ from the original location.
 </p>
</div>
<div class="col-12">
 <label class="form-label" for="transferRemarks">Remarks <span class="optional-label">(optional)</span></label>
 <textarea class="form-control" id="transferRemarks" name="remarks" rows="2"></textarea>
</div>
