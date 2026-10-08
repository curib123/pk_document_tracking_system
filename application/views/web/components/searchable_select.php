<?php
/** Native <select> remains the form value. jQuery enhances it when available. */
$selectId = (string) ($selectId ?? 'pk-select');
$selectName = (string) ($selectName ?? '');
$selectLabel = (string) ($selectLabel ?? 'Option');
$selectValue = (string) ($selectValue ?? '');
$selectOptions = is_array($selectOptions ?? null) ? $selectOptions : [];
$selectRequired = !empty($selectRequired);
$selectError = (string) ($selectError ?? '');
$selectSearchAction = (string) ($selectSearchAction ?? '');
$selectSearchTerm = (string) ($selectSearchTerm ?? '');
$selectAutofocus = !empty($selectAutofocus);
$selectMore = !empty($selectMore);
$selectPlaceholder = (string) ($selectPlaceholder ?? ('Choose ' . strtolower($selectLabel)));
?>
<div class="pk-select-shell">
    <select id="<?= ui_escape($selectId) ?>" name="<?= ui_escape($selectName) ?>"
            class="form-select <?= $selectError ? 'is-invalid' : '' ?>"
            data-pk-searchable="true" data-label="<?= ui_escape($selectLabel) ?>"
            data-pk-autofocus="<?= $selectAutofocus ? 'true' : 'false' ?>"
            data-pk-search-term="<?= ui_escape($selectSearchTerm) ?>"
            <?= $selectSearchAction !== '' ? 'data-lookup-action="' . ui_escape($selectSearchAction) . '"' : '' ?>
            <?= $selectSearchAction !== '' ? 'data-lookup-name="lookup_q[' . ui_escape($selectName) . ']"' : '' ?>
            <?= $selectRequired ? 'required data-pk-required="true"' : '' ?>
            <?= $selectError !== '' ? 'aria-invalid="true" aria-describedby="' . ui_escape($selectId . '-error') . '"' : '' ?>>
        <option value=""><?= ui_escape($selectPlaceholder) ?></option>
        <?php foreach ($selectOptions as $choice): ?>
            <?php $optionValue = (string) ($choice['value'] ?? $choice['id'] ?? ''); ?>
            <option value="<?= ui_escape($optionValue) ?>" <?= $selectValue !== '' && $selectValue === $optionValue ? 'selected' : '' ?>>
                <?= ui_escape((string) ($choice['label'] ?? $optionValue)) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($selectSearchAction !== ''): ?>
    <div class="pk-lookup-fallback mt-2" data-pk-lookup-fallback>
        <div class="input-group input-group-sm">
            <input class="form-control" type="search"
                   name="lookup_q[<?= ui_escape($selectName) ?>]"
                   aria-label="Search <?= ui_escape($selectLabel) ?>"
                   value="<?= ui_escape($selectSearchTerm) ?>" maxlength="100" placeholder="Search all options">
            <button type="submit" class="btn btn-outline-secondary"
                    name="lookup_field" value="<?= ui_escape($selectName) ?>"
                    formaction="<?= ui_escape($selectSearchAction) ?>"
                    formmethod="post" formnovalidate>Find</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($selectMore): ?>
        <div class="form-text">More choices available — search the full list by name.</div>
    <?php endif; ?>
    <?php if ($selectError !== ''): ?>
        <div class="invalid-feedback d-block" id="<?= ui_escape($selectId . '-error') ?>"><?= ui_escape($selectError) ?></div>
    <?php endif; ?>
</div>
