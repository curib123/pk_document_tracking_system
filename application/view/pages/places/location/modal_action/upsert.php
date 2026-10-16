<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Database-backed Location Upsert: no hardcoded area/specific/asset records.
?>
<div class="col-12">
    <h3 class="fs-6 fw-bold mb-1">Location details</h3>
    <p class="text-secondary small mb-0">Name the Location and set its order within the selected Specific.</p>
</div>
<div class="col-md-7">
    <label class="form-label" for="location-name">Location Name</label>
    <input class="form-control" id="location-name" name="name" type="text" maxlength="150"
        placeholder="e.g. Location A" required autocomplete="off">
</div>
<div class="col-md-5">
    <label class="form-label" for="location-sequence">Sequence <span class="optional-label">(auto if empty)</span></label>
    <input class="form-control" id="location-sequence" name="sequence_number" type="number"
        min="1" max="1000000" step="1" placeholder="e.g. 1" autocomplete="off">
    <div class="form-text">Unique within this Specific. A = 1, B = 2, C = 3.</div>
</div>

<div class="col-md-4">
    <label class="form-label" for="location-area">Area</label>
    <select class="form-select" id="location-area" name="area_id" data-location-level="area" data-searchable required autocomplete="off">
        <option value="">No area</option>
        <?php foreach ($options['areas'] as $area): ?>
            <option value="<?= (int) $area['id'] ?>"><?= html_escape($area['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="location-specific">Specific</label>
    <select class="form-select" id="location-specific" name="specific_id" data-location-level="specific" data-searchable
        required autocomplete="off">
        <option value="">No specific</option>
        <?php foreach ($options['specifics'] as $specific): ?>
            <option value="<?= (int) $specific['id'] ?>" data-area-id="<?= (int) $specific['area_id'] ?>">
                <?= html_escape($specific['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="location-asset">Asset Number <span class="optional-label">(optional — can be
            empty)</span></label>
    <select class="form-select" id="location-asset" name="asset_id" data-location-level="asset" data-searchable autocomplete="off">
        <option value="">No asset / not assigned</option>
        <?php foreach ($options['assets'] as $asset): ?>
            <option value="<?= (int) $asset['id'] ?>" data-specific-id="<?= (int) $asset['specific_id'] ?>"
                data-area-id="<?= (int) $asset['area_id'] ?>"><?= html_escape($asset['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <div class="form-text">A Location can be saved without an Asset Number.</div>
</div>
<div class="col-12">
    <p class="form-text mb-0" role="status" id="location-hierarchy-help">
        Select an Area and Specific. Asset Number is optional.
    </p>
</div>
<div class="col-md-6">
    <label class="form-label" for="location-archive-date">Archive Date <span
            class="optional-label">(optional)</span></label>
    <input class="form-control" id="location-archive-date" name="archive_date" type="date" autocomplete="off">
</div>