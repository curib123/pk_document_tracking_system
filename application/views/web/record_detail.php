<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0"><?= ui_escape($page_title) ?></h1>
    <a href="<?= site_url('web/records/' . $module) ?>" class="btn btn-outline-secondary">Back to list</a>
</div>
<div class="card">
    <div class="card-body">
        <dl class="row mb-0">
            <?php foreach ($detail['row'] as $key => $value): ?>
                <?php
                // Relation IDs stay internal; show their display labels instead.
                if ($key === 'id' || $key === 'version' || str_ends_with($key, '_id')) {
                    continue;
                }
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                ?>
                <dt class="col-12 col-md-4 text-body-secondary"><?= ui_escape(ucwords(str_replace('_', ' ', $key))) ?></dt>
                <dd class="col-12 col-md-8 text-break"><?= ui_escape($value ?? '—') ?></dd>
            <?php endforeach; ?>
        </dl>
    </div>
</div>
<?php foreach ($detail['related'] as $section => $items): ?>
    <?php if (!is_array($items) || !array_is_list($items)): ?>
        <?php continue; ?>
    <?php endif; ?>
    <section class="card mt-3">
        <div class="card-body">
            <h2 class="h5"><?= ui_escape(ucwords(str_replace('_', ' ', $section))) ?></h2>
            <?php if (!$items): ?><p class="text-body-secondary mb-0">No history available.</p><?php endif; ?>
            <?php foreach ($items as $item): ?>
                <?php if (!is_array($item)): continue; endif; ?>
                <dl class="row border-bottom py-2 mb-0">
                <?php foreach ($item as $key => $value): ?>
                    <?php if ($key === 'id' || $key === 'version' || str_ends_with($key, '_id')): continue; endif; ?>
                    <dt class="col-12 col-md-4"><?= ui_escape(ucwords(str_replace('_', ' ', $key))) ?></dt>
                    <dd class="col-12 col-md-8 text-break"><?= ui_escape(is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?></dd>
                <?php endforeach; ?>
                </dl>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
