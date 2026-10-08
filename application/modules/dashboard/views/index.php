<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Workspace overview</h1>
        <p class="text-body-secondary mb-0">Your documents, requests and daily responsibilities in one place.</p>
    </div>
    <span class="badge rounded-pill text-bg-light border px-3 py-2"><?= ui_escape(date('F j, Y')) ?></span>
</div>
<div class="row g-3 mb-4">
    <?php
    $titles = [
        'softcopy' => 'Softcopy documents',
        'hardcopy' => 'Hardcopy documents',
        'my_requests' => 'My requests',
    ];
    foreach ($titles as $key => $title):
        if (!isset($summary[$key]) || !is_array($summary[$key])) {
            continue;
        }
        $total = 0;
        foreach ($summary[$key] as $item) {
            $total += (int) ($item['total'] ?? 0);
        }
    ?>
        <div class="col-12 col-sm-6 col-xl-4">
            <section class="card pk-overview-kpi h-100" aria-label="<?= ui_escape($title) ?>">
                <div class="card-body d-flex align-items-start gap-3 p-4">
                    <span class="pk-kpi-icon"><?= pk_web_icon($key) ?></span>
                    <div class="flex-grow-1">
                        <p class="text-body-secondary mb-2"><?= ui_escape($title) ?></p>
                        <strong class="display-6 d-block fw-bold"><?= $total ?></strong>
                        <?php if (!empty($navigation[$key])): ?>
                            <a class="small text-decoration-none fw-semibold" href="<?= site_url('web/records/' . $key) ?>">View records <span aria-hidden="true">→</span></a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    <?php endforeach; ?>
</div>
<?php
$pending = (int) ($summary['pending_receipts'] ?? 0);
$unread = (int) ($summary['unread_notifications'] ?? 0);
?>
<?php if ($pending > 0 || $unread > 0): ?>
<div class="row g-3 mb-4">
    <?php if ($pending > 0 && !empty($navigation['transfers'])): ?>
        <div class="col-12 col-lg-6">
            <a class="alert alert-warning d-flex align-items-center gap-3 text-decoration-none h-100" href="<?= site_url('web/records/transfers') ?>">
                <?= pk_web_icon('transfers') ?>
                <span><strong><?= $pending ?> pending receipts</strong><br><small>Review documents awaiting your confirmation.</small></span>
            </a>
        </div>
    <?php endif; ?>
    <?php if ($unread > 0 && !empty($navigation['notifications'])): ?>
        <div class="col-12 col-lg-6">
            <a class="alert alert-info d-flex align-items-center gap-3 text-decoration-none h-100" href="<?= site_url('web/records/notifications') ?>">
                <?= pk_web_icon('notifications') ?>
                <span><strong><?= $unread ?> unread notifications</strong><br><small>Catch up on recent activity.</small></span>
            </a>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<section class="card" aria-labelledby="pk-quick-access">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h5 fw-bold mb-1" id="pk-quick-access">Quick access</h2>
            <p class="small text-body-secondary mb-0">Go directly to the tasks and records you use most.</p>
        </div>
    </div>
    <div class="card-body p-3">
        <div class="row g-2">
            <?php
            $links = ['my_tasks','my_requests','softcopy','hardcopy','areas','workflows'];
            $catalogKeys = ['areas','specifics','assets','locations','categories'];
            $any = false;
            foreach ($links as $key):
                if (empty($navigation[$key])) {
                    continue;
                }
                $any = true;
                $module = $navigation[$key];
                $path = in_array($key, $catalogKeys, true)
                    ? 'web/catalog/' . $key : 'web/records/' . $key;
            ?>
                <div class="col-12 col-sm-6 col-xl-4">
                    <a class="pk-quick-link" href="<?= site_url($path) ?>">
                        <span class="pk-quick-icon"><?= pk_web_icon($key) ?></span>
                        <span class="flex-grow-1 fw-semibold"><?= ui_escape($module['label']) ?></span>
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (!$any): ?>
                <div class="col"><p class="text-body-secondary mb-0">No shortcuts are available for your current permissions.</p></div>
            <?php endif; ?>
        </div>
    </div>
</section>
