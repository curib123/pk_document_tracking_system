<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?= ui_escape($definition['label']) ?></h1>
        <p class="text-body-secondary mb-0">Manage catalogue records and maintain consistent document references.</p>
    </div>
    <?php if (!empty($can_add)): ?>
        <a class="btn btn-primary" href="<?= site_url('web/catalog/' . $module . '/new') ?>">
            <?= pk_web_icon('add') ?> Add record
        </a>
    <?php endif; ?>
</div>
<?php $tableKind = 'catalog'; ?>
<?php require __DIR__ . '/components/data_table.php'; ?>
