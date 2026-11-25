<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="col-md-6" data-editable-options
    data-options="<?= html_escape(json_encode(['subsidiary' => $subsidiary_options], JSON_INVALID_UTF8_SUBSTITUTE)) ?>">
    <label class="form-label" for="<?= html_escape($subsidiary_field_id) ?>">Subsidiary <span class="optional-label">(optional)</span></label>
    <div class="searchable-select">
        <input class="form-control searchable-input" type="text" id="<?= html_escape($subsidiary_field_id) ?>"
            name="subsidiary_name" maxlength="150" autocomplete="off" role="combobox" data-combobox="subsidiary"
            aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false"
            placeholder="Select or type a subsidiary">
    </div>
</div>
