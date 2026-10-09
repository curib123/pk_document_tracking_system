<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Database-backed Location Upsert: no hardcoded area/specific/asset records.
?>
<div class="col-12">
    <h3 class="fs-6 fw-bold mb-1">Location details</h3>
    <p class="text-secondary small mb-0">Enter a unique name and location code.</p>
</div>
<div class="col-md-7">
    <label class="form-label" for="location-name">Location Name</label>
    <input class="form-control" id="location-name" name="name" type="text"
           maxlength="150" placeholder="e.g. Archive Shelf A" required>
</div>
<div class="col-md-5">
    <label class="form-label" for="location-code">Location Code</label>
    <input class="form-control" id="location-code" name="code" type="text"
           maxlength="100" placeholder="e.g. ARC-SHELF-A" required>
    <div class="form-text">Code must be unique in all locations.</div>
</div>

<div class="col-md-4">
    <label class="form-label" for="location-area">Area</label>
    <select class="form-select" id="location-area" name="area_id"
            data-location-level="area" data-searchable>
        <option value="">No area</option>
        <?php foreach ($options['areas'] as $area): ?>
            <option value="<?= (int)$area['id'] ?>"><?= html_escape($area['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="location-specific">Specific</label>
    <select class="form-select" id="location-specific" name="specific_id"
            data-location-level="specific" data-searchable>
        <option value="">No specific</option>
        <?php foreach ($options['specifics'] as $specific): ?>
            <option value="<?= (int)$specific['id'] ?>"
                    data-area-id="<?= (int)$specific['area_id'] ?>"><?= html_escape($specific['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="location-asset">Asset Number</label>
    <select class="form-select" id="location-asset" name="asset_id"
            data-location-level="asset" data-searchable>
        <option value="">No asset</option>
        <?php foreach ($options['assets'] as $asset): ?>
            <option value="<?= (int)$asset['id'] ?>"
                    data-specific-id="<?= (int)$asset['specific_id'] ?>"
                    data-area-id="<?= (int)$asset['area_id'] ?>"><?= html_escape($asset['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-12">
    <p class="form-text mb-0" role="status" id="location-hierarchy-help">
        All parent selections are optional unless you select a child.
    </p>
</div>
<div class="col-md-6">
    <label class="form-label" for="location-archive-date">Archive Date <span class="optional-label">(optional)</span></label>
    <input class="form-control" id="location-archive-date" name="archive_date" type="date">
</div>
