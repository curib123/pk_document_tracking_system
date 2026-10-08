<?php
$activeRoute = (string) ($current_route ?? '');
$catalogKeys = ['areas', 'specifics', 'assets', 'locations', 'categories'];
$groups = [];
foreach ($navigation as $item) {
    $groups[$item['navigation_group']][] = $item;
}
?>
<div class="pk-sidebar-inner">
    <div class="pk-sidebar-top">
        <a href="<?= site_url('web') ?>" class="pk-sidebar-home <?= pk_web_route($activeRoute, 'web') && !str_starts_with($activeRoute, 'web/') ? 'is-active' : '' ?>"
           <?= $activeRoute === 'web' || $activeRoute === '' ? 'aria-current="page"' : '' ?>>
            <?= pk_web_icon('dashboard') ?> <span>Overview</span>
        </a>
    </div>

    <?php foreach ($groups as $group => $items): ?>
        <?php
        $groupActive = false;
        foreach ($items as $item) {
            $target = in_array($item['key'], $catalogKeys, true)
                ? 'web/catalog/' . $item['key']
                : 'web/records/' . $item['key'];
            if (pk_web_route($activeRoute, $target)) {
                $groupActive = true;
                break;
            }
        }
        // Only active section is open by default; leave Overview clean and compact.
        ?>
        <details class="pk-sidebar-group" <?= $groupActive ? 'open' : '' ?>>
            <summary class="pk-sidebar-heading pk-sidebar-toggle">
                <span><?= ui_escape($group) ?></span>
                <span aria-hidden="true"><?= pk_web_icon('chevron') ?></span>
            </summary>
            <nav class="pk-sidebar-links" aria-label="<?= ui_escape($group) ?>">
                <?php foreach ($items as $item): ?>
                    <?php
                    $target = in_array($item['key'], $catalogKeys, true)
                        ? 'web/catalog/' . $item['key']
                        : 'web/records/' . $item['key'];
                    $active = pk_web_route($activeRoute, $target);
                    ?>
                    <a href="<?= site_url($target) ?>" class="pk-sidebar-link <?= $active ? 'is-active' : '' ?>"
                       <?= $active ? 'aria-current="page"' : '' ?>>
                        <?= pk_web_icon($item['key']) ?>
                        <span><?= ui_escape($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </details>
    <?php endforeach; ?>

    <div class="pk-sidebar-bottom">
        <a class="pk-sidebar-link <?= pk_web_route($activeRoute, 'web/password') ? 'is-active' : '' ?>"
           href="<?= site_url('web/password') ?>" <?= pk_web_route($activeRoute, 'web/password') ? 'aria-current="page"' : '' ?>>
            <?= pk_web_icon('settings') ?> <span>Account settings</span>
        </a>
        <a class="pk-sidebar-link" href="<?= site_url('app') ?>">
            <?= pk_web_icon('files') ?> <span>Classic operations</span>
        </a>
        <p class="pk-sidebar-caption">PK Document Control · CodeIgniter 3</p>
    </div>
</div>
