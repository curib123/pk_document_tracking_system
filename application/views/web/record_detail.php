<?php
require_once APPPATH . 'helpers/web_display_helper.php';
$display = pk_web_display_fields($detail['row']);
$descriptions = is_array($detail['row']['request_details'] ?? null)
    ? pk_web_request_fields($detail['row']['request_details'])
    : [];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?= ui_escape($page_title) ?></h1>
        <p class="text-body-secondary mb-0">A readable view of this record and its activity.</p>
    </div>
    <a href="<?= site_url('web/records/' . $module) ?>" class="btn btn-outline-secondary">Back to records</a>
</div>
<section class="card mb-3" aria-labelledby="pk-record-info">
    <div class="card-header py-3"><h2 class="h5 mb-0" id="pk-record-info">Record information</h2></div>
    <div class="card-body">
        <dl class="row g-2 mb-0">
            <?php foreach ($display as $item): ?>
                <dt class="col-12 col-sm-4 text-body-secondary"><?= ui_escape($item['label']) ?></dt>
                <dd class="col-12 col-sm-8 text-break"><?= ui_escape($item['value']) ?></dd>
            <?php endforeach; ?>
        </dl>
    </div>
</section>
<?php if ($descriptions): ?>
<section class="card mb-3" aria-labelledby="pk-request-data">
    <div class="card-header py-3"><h2 class="h5 mb-0" id="pk-request-data">Request details</h2></div>
    <div class="card-body">
        <dl class="row g-2 mb-0">
        <?php foreach ($descriptions as $item): ?>
            <dt class="col-12 col-sm-4 text-body-secondary"><?= ui_escape($item['label']) ?></dt>
            <dd class="col-12 col-sm-8 text-break"><?= ui_escape($item['value']) ?></dd>
        <?php endforeach; ?>
        </dl>
    </div>
</section>
<?php endif; ?>
<?php foreach ($detail['related'] as $section => $entries): ?>
    <?php
    if (!is_array($entries) || ($entries && array_keys($entries) !== range(0, count($entries)-1))) {
        continue;
    }
    $sectionTitle = ucwords(str_replace('_', ' ', (string) $section));
    $sectionId = 'pk-section-' . preg_replace('/[^a-z0-9]/', '-', strtolower((string) $section));
    ?>
    <section class="card mb-3" aria-labelledby="<?= ui_escape($sectionId) ?>">
        <div class="card-header py-3">
            <h2 class="h5 mb-0" id="<?= ui_escape($sectionId) ?>"><?= ui_escape($sectionTitle) ?> <span class="text-body-secondary small">(<?= count($entries) ?>)</span></h2>
        </div>
        <div class="card-body">
            <?php if (!$entries): ?><p class="text-body-secondary mb-0">No history recorded.</p><?php endif; ?>
            <?php foreach (array_slice($entries, 0, 50) as $entryIndex => $entry): ?>
                <?php if (!is_array($entry)): continue; endif; ?>
                <div class="border-bottom mb-3 pb-2">
                    <h3 class="h6 mb-2 text-body-secondary">Entry <?= $entryIndex + 1 ?></h3>
                    <dl class="row g-1 mb-0">
                    <?php foreach (pk_web_display_fields($entry) as $item): ?>
                        <dt class="col-12 col-sm-4 text-body-secondary"><?= ui_escape($item['label']) ?></dt>
                        <dd class="col-12 col-sm-8 text-break"><?= ui_escape($item['value']) ?></dd>
                    <?php endforeach; ?>
                    </dl>
                </div>
            <?php endforeach; ?>
            <?php if (count($entries) > 50): ?>
                <p class="text-body-secondary small mb-0">Showing 50 of <?= count($entries) ?> entries.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
