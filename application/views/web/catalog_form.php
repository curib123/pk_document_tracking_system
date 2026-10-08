<h1 class="h3 mb-3"><?= ui_escape($page_title) ?></h1>
<form action="<?= site_url('web/catalog/' . $module . '/save') ?>" method="post" class="card card-body shadow-sm">
    <input type="hidden" name="csrf" value="<?= ui_escape($csrf) ?>">
    <?php if (!empty($record['id'])): ?>
        <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
        <input type="hidden" name="version" value="<?= (int) $record['version'] ?>">
    <?php endif; ?>
    <div class="row g-3">
    <?php foreach ($definition['fields'] as $field): ?>
        <?php
        $name = $field['name'];
        $value = $record[$name] ?? '';
        $required = !empty($field['required']);
        $type = $field['type'];
        ?>
        <div class="col-12 col-lg-6">
            <?php if ($type === 'checkbox'): ?>
                <div class="form-check mt-4">
                    <input type="hidden" name="<?= ui_escape($name) ?>" value="0">
                    <input class="form-check-input" type="checkbox" id="field_<?= ui_escape($name) ?>" name="<?= ui_escape($name) ?>" value="1" <?= !$record || (int) $value === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="field_<?= ui_escape($name) ?>"><?= ui_escape($field['label']) ?></label>
                </div>
            <?php else: ?>
                <label class="form-label" for="field_<?= ui_escape($name) ?>"><?= ui_escape($field['label']) ?></label>
                <?php if ($type === 'lookup'): ?>
                    <select class="form-select" id="field_<?= ui_escape($name) ?>" name="<?= ui_escape($name) ?>" <?= $required ? 'required' : '' ?>>
                        <option value="">-- Select --</option>
                        <?php foreach (($lookups[$name]['options'] ?? []) as $option): ?>
                            <option value="<?= (int) $option['id'] ?>" <?= (int) $value === (int) $option['id'] ? 'selected' : '' ?>><?= ui_escape($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($lookups[$name]['more'])): ?>
                        <div class="form-text">Only the first 100 options are shown. Use the existing workspace for larger catalogues until native lookup search is available.</div>
                    <?php endif; ?>
                <?php elseif ($type === 'textarea'): ?>
                    <textarea class="form-control" id="field_<?= ui_escape($name) ?>" name="<?= ui_escape($name) ?>" rows="3" <?= $required ? 'required' : '' ?>><?= ui_escape($value) ?></textarea>
                <?php else: ?>
                    <input class="form-control" type="<?= $type === 'date' ? 'date' : 'text' ?>" id="field_<?= ui_escape($name) ?>" name="<?= ui_escape($name) ?>" value="<?= ui_escape($value) ?>" <?= $required ? 'required' : '' ?>>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="<?= site_url('web/catalog/' . $module) ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
