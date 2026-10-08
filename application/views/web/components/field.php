<?php
$name = (string) $field['name'];
$type = (string) ($field['type'] ?? 'text');
$id = 'pk-field-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
$label = (string) $field['label'];
$required = !empty($field['required']);
$value = $record[$name] ?? '';
$error = (string) ($form_error['fields'][$name] ?? '');
?>
<div class="col-12 col-md-6">
    <?php if ($type === 'checkbox'): ?>
        <div class="form-check form-switch mt-3 pt-2">
            <input type="hidden" name="<?= ui_escape($name) ?>" value="0">
            <input class="form-check-input" type="checkbox" id="<?= ui_escape($id) ?>"
                   name="<?= ui_escape($name) ?>" value="1"
                   <?= !$record || (int) $value === 1 ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="<?= ui_escape($id) ?>"><?= ui_escape($label) ?></label>
        </div>
        <?php if ($error !== ''): ?><p class="invalid-feedback d-block"><?= ui_escape($error) ?></p><?php endif; ?>
    <?php else: ?>
        <label class="form-label" for="<?= ui_escape($id) ?>">
            <?= ui_escape($label) ?>
            <?php if ($required): ?><span class="pk-required" aria-label="required">*</span><?php endif; ?>
        </label>
        <?php if ($type === 'lookup'): ?>
            <?php
            $selectId = $id;
            $selectName = $name;
            $selectLabel = $label;
            $selectValue = (string) $value;
            $selectOptions = $lookups[$name]['options'] ?? [];
            $selectRequired = $required;
            $selectError = $error;
            $selectSearchAction = site_url('web/catalog/' . $module . '/lookup');
            $selectSearchTerm = ($lookup_field ?? '') === $name ? (string) ($lookup_query ?? '') : '';
            $selectAutofocus = ($lookup_field ?? '') === $name;
            $selectMore = !empty($lookups[$name]['more']);
            require __DIR__ . '/searchable_select.php';
            ?>
        <?php elseif ($type === 'textarea'): ?>
            <textarea class="form-control <?= $error !== '' ? 'is-invalid' : '' ?>"
                      id="<?= ui_escape($id) ?>" name="<?= ui_escape($name) ?>" rows="4"
                      <?= $required ? 'required' : '' ?>><?= ui_escape($value) ?></textarea>
        <?php else: ?>
            <input class="form-control <?= $error !== '' ? 'is-invalid' : '' ?>"
                   type="<?= $type === 'date' ? 'date' : 'text' ?>"
                   id="<?= ui_escape($id) ?>" name="<?= ui_escape($name) ?>"
                   value="<?= ui_escape($value) ?>" <?= $required ? 'required' : '' ?>>
        <?php endif; ?>
        <?php if ($error !== '' && $type !== 'lookup'): ?>
            <p class="invalid-feedback d-block" id="<?= ui_escape($id . '-error') ?>"><?= ui_escape($error) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
