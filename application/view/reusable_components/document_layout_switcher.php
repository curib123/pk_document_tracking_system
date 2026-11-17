<nav class="document-layout-switch" aria-label="<?= html_escape($layout_label ?? 'Choose document view') ?>">
    <?php foreach (['table' => ['Table', 'fa-table-list', 'table view'], 'folder' => ['Folder', 'fa-folder-tree', 'folder tree view']] as $layout => [$label, $icon, $accessible]): ?>
        <a href="<?= html_escape($layoutLink($layout)) ?>"
            class="document-layout-choice <?= $activeLayout === $layout ? 'is-active' : '' ?>"
            aria-label="Switch to <?= $accessible ?>" <?= $activeLayout === $layout ? 'aria-current="page"' : '' ?>>
            <span class="document-layout-choice-icon"><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></span>
            <span class="document-layout-choice-copy"><?= $label ?></span>
        </a>
    <?php endforeach; ?>
</nav>
