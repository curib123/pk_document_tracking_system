<div class="mb-4">
    <h1 class="h3 fw-bold mb-1"><?= ui_escape($definition['label']) ?></h1>
    <p class="text-body-secondary mb-0">Browse records available to your account. Filtering and paging are performed by the server.</p>
</div>
<?php $tableKind = 'records'; ?>
<?php require __DIR__ . '/components/data_table.php'; ?>
