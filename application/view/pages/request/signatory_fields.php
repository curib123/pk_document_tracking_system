<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Signatories References: optional, repeatable rows of three editable searchable
// dropdowns. Behaviour lives in assets/js/signatory-references.js.
// Dropdown options are the distinct values already saved (no master tables).
if (!isset($signatory_options)) {
    require_once APPPATH . 'services/softcopy/signatory_reference_service.php';
    $signatory_options = (new Signatory_reference_service())->options();
}
$signatoryFields = [
    'title' => ['Signatory Title', 'signatory_title[]', 'Select or type a title'],
    'full_name' => ['Signatory Full Name', 'signatory_full_name[]', 'Select or type a full name'],
    'position' => ['Signatory Position', 'signatory_position[]', 'Select or type a position']
];
?>
<div class="col-12 signatory-references" data-signatory-references
    data-options="<?= html_escape(json_encode($signatory_options, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?>">
    <div class="signatory-references-head">
        <span class="form-label mb-0">Signatories References <span class="optional-label">(optional)</span></span>
        <button class="btn btn-light btn-sm" type="button" data-signatory-add>Add Signatory</button>
    </div>
    <small class="form-text" data-signatory-empty>
        No signatories added. Use Add Signatory to list who signed or approved this revision.
    </small>
    <div class="signatory-rows" data-signatory-rows></div>
    <small class="form-text">Each field lets you pick a previously saved value or type a new one. The fields
        work independently, so choosing a name never changes the title or position.</small>
    <template data-signatory-template>
        <div class="signatory-row" data-signatory-row>
            <div class="signatory-row-head">
                <strong data-signatory-number>Signatory</strong>
                <button class="btn btn-link btn-sm p-0" type="button" data-signatory-remove>Remove</button>
            </div>
            <div class="signatory-row-fields">
                <?php foreach ($signatoryFields as $field => [$label, $inputName, $placeholder]): ?>
                    <div>
                        <label class="form-label" data-for="<?= $field ?>"><?= html_escape($label) ?></label>
                        <div class="searchable-select">
                            <input class="form-control searchable-input" type="text" name="<?= $inputName ?>"
                                maxlength="150" required autocomplete="off" role="combobox"
                                aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false"
                                data-combobox="<?= $field ?>" placeholder="<?= html_escape($placeholder) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </template>
</div>
