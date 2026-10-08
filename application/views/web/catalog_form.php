<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= site_url('web') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('web/catalog/' . $module) ?>"><?= ui_escape($definition['label']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= !empty($record['id']) ? 'Edit' : 'Add' ?></li>
    </ol>
</nav>
<?php ob_start(); ?>
<?php if (!empty($form_error['message'])): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Could not save.</strong> <?= ui_escape($form_error['message']) ?>
        <p class="small mb-0">Your entered values are preserved.</p>
    </div>
<?php endif; ?>
<p class="text-body-secondary mb-4">Fields marked <span class="pk-required">*</span> are required.</p>
<div class="row g-3">
    <?php foreach ($definition['fields'] as $field): ?>
        <?php require __DIR__ . '/components/field.php'; ?>
    <?php endforeach; ?>
</div>
<?php
$modalContentHtml = ob_get_clean();
$modalId = 'pk-catalog-form-modal';
$modalTitle = (string) $page_title;
$modalTone = 'info';
$modalAutoOpen = true;
$modalMessage = '';
$modalAction = site_url('web/catalog/' . $module . '/save');
$modalFields = ['csrf' => $csrf];
if (!empty($record['id'])) {
    $modalFields['id'] = (int) $record['id'];
    $modalFields['version'] = (int) ($record['version'] ?? 0);
}
$modalSubmit = !empty($record['id']) ? 'Save changes' : 'Create record';
$modalBack = site_url('web/catalog/' . $module);
require __DIR__ . '/components/modal.php';
?>
