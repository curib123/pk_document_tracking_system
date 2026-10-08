<div class="mb-4">
    <h1 class="h3 fw-bold mb-1"><?= ui_escape($definition['label']) ?></h1>
    <p class="text-body-secondary mb-0">Browse records available to your account. Filtering and paging are performed by the server.</p>
    <?php if ($module === 'hardcopy' && !empty($navigation['my_requests'])): ?>
        <a class="btn btn-outline-primary mt-3" href="<?= site_url('web/records/my_requests') . '?type=hardcopy_create&status=draft' ?>">
            View my hardcopy draft requests
        </a>
    <?php endif; ?>
</div>
<?php $tableKind = 'records'; ?>
<?php require __DIR__ . '/components/data_table.php'; ?>
