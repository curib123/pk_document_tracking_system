<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$disposal_field_prefix=$disposal_field_prefix??'disposal';
?>
<div class="col-md-6">
  <label class="form-label" for="<?= html_escape($disposal_field_prefix) ?>Reason">Disposal Reason</label>
  <select class="form-select" id="<?= html_escape($disposal_field_prefix) ?>Reason" name="disposal_reason"
          data-disposal-reason required>
    <option value="">Select disposal reason</option>
    <option value="shred">Shred</option>
    <option value="scratch">Scratch</option>
    <option value="other">Other</option>
  </select>
</div>
<div class="col-md-6" data-disposal-other hidden>
  <label class="form-label" for="<?= html_escape($disposal_field_prefix) ?>Other">Other Reason</label>
  <input class="form-control" id="<?= html_escape($disposal_field_prefix) ?>Other" name="disposal_other"
         maxlength="250" placeholder="Enter disposal reason">
</div>
