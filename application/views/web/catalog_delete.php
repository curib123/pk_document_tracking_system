<?php
$recordLabel = (string) ($record['name'] ?? $record['asset_number'] ?? 'this record');
ob_start();
?>
<div class="alert alert-warning">
    Records referenced by other documents cannot be deleted. The service validates all references.
</div>
<div class="mb-2">
    <label class="form-label" for="pk-delete-reason">Reason (optional)</label>
    <textarea class="form-control" id="pk-delete-reason" name="reason" maxlength="2000" rows="3"
              placeholder="Reason for removing the record"></textarea>
</div>
<?php
$modalContentHtml = ob_get_clean();
$modalId = 'pk-catalog-delete-modal';
$modalTitle = 'Delete ' . $definition['label'] . ' record?';
$modalTone = 'danger';
$modalMessage = 'You are about to delete ' . $recordLabel . '. This action cannot be undone.';
$modalAutoOpen = true;
$modalAction = site_url('web/catalog/' . $module . '/remove');
$modalFields = ['csrf' => $csrf, 'id' => (int) $record['id'], 'version' => (int) $record['version']];
$modalSubmit = 'Delete record';
$modalBack = site_url('web/catalog/' . $module);
require __DIR__ . '/components/modal.php';
?>
