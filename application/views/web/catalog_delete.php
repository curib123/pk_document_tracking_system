<h1 class="h3 mb-3">Delete <?= ui_escape($definition['label']) ?> record?</h1>
<div class="card card-body shadow-sm">
    <p>Delete <strong><?= ui_escape($record['name'] ?? $record['asset_number'] ?? 'this record') ?></strong>?</p>
    <p class="text-body-secondary">Referenced records cannot be deleted. All checks remain in the catalogue service.</p>
    <form method="post" action="<?= site_url('web/catalog/' . $module . '/remove') ?>">
        <input type="hidden" name="csrf" value="<?= ui_escape($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
        <input type="hidden" name="version" value="<?= (int) $record['version'] ?>">
        <label for="delete_reason" class="form-label">Reason (optional)</label>
        <textarea class="form-control mb-3" id="delete_reason" name="reason" rows="2" maxlength="2000"></textarea>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger">Confirm deletion</button>
            <a class="btn btn-outline-secondary" href="<?= site_url('web/catalog/' . $module) ?>">Cancel</a>
        </div>
    </form>
</div>
