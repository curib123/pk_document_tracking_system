<?php
// Single source of truth for row actions in table, grid and folder layouts.
// Text labels are always visible; icons are decorative only.
?>
<div class="record-actions-horizontal">
    <?php foreach ($dt_row['buttons'] ?? [] as $button):
        $type = $button['type'] ?? '';
        $label = $button['label'] ?? [
            'view' => 'View',
            'edit' => 'Edit',
            'steps' => 'Step Actions',
            'grants' => 'Manage Access',
            'history' => 'File History',
            'upload' => 'Upload',
            'review' => 'Review File',
            'download' => 'Download',
            'print' => 'Print'
        ][$type] ?? 'Action';
        ?>
        <?php if ($type === 'view'): ?>
            <button type="button" class="btn-icon record-action record-action-view js-view" data-bs-toggle="modal" data-bs-target="#viewModal" title="<?= html_escape($label) ?>"
                aria-label="<?= html_escape($label) ?>"
                <?php if (!empty($button['view_url'])): ?>data-view-url="<?= html_escape($button['view_url']) ?>"<?php endif; ?>
                data-display="<?= html_escape(json_encode($dt_row['display'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)) ?>"
                data-title="<?= html_escape($button['label'] ?? 'View Details') ?>">
                <i class="<?= html_escape($button['icon'] ?? 'fa-regular fa-eye') ?>" aria-hidden="true"></i><span><?= html_escape($label) ?></span>
            </button>
        <?php elseif ($type === 'edit'): ?>
            <button type="button" class="btn-icon record-action record-action-edit js-edit" title="<?= html_escape($label) ?>"
                aria-label="<?= html_escape($label) ?>" data-bs-toggle="modal"
                data-bs-target="<?= html_escape($button['modal'] ?? '#editModal') ?>"
                data-target="<?= html_escape($button['form'] ?? '#editForm') ?>"
                data-record="<?= html_escape(json_encode($dt_row['record'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)) ?>"
                data-title="Edit Record">
                <i class="fa-solid fa-pen" aria-hidden="true"></i><span><?= html_escape($label) ?></span>
            </button>
        <?php elseif ($type === 'steps'): ?>
            <button type="button" class="btn-icon record-action record-action-manage" data-bs-toggle="modal"
                data-bs-target="#workflowStepsModal-<?= (int) $dt_row['id'] ?>"
                aria-label="Step Actions for <?= html_escape($dt_row['cells']['name'] ?? 'workflow') ?>">
                <i class="fa-solid fa-list-check" aria-hidden="true"></i><span><?= html_escape($label) ?></span>
            </button>
        <?php elseif ($type === 'grants'): ?>
            <button type="button" class="btn-icon record-action record-action-manage" title="<?= html_escape($label) ?>"
                aria-label="<?= html_escape($label) ?>" data-bs-toggle="modal"
                data-bs-target="#grantModal-<?= (int) $dt_row['id'] ?>">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i><span><?= html_escape($label) ?></span>
            </button>
        <?php elseif ($type === 'history'): ?>
            <button type="button" class="btn-icon record-action record-action-history js-file-history"
                title="<?= html_escape($label) ?>" aria-label="<?= html_escape($label) ?>" data-bs-toggle="modal"
                data-bs-target="#fileHistoryModal"
                data-files="<?= html_escape(json_encode($button['files'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)) ?>">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span><?= html_escape($label) ?></span>
            </button>
        <?php elseif ($type === 'upload'): ?>
            <button type="button" class="btn-icon record-action record-action-upload js-upload"
                title="<?= html_escape($label) ?>" aria-label="<?= html_escape($label) ?>" data-bs-toggle="modal"
                data-bs-target="#uploadModal" data-document-id="<?= (int) $dt_row['id'] ?>"
                data-document-name="<?= html_escape($dt_row['cells']['title'] ?? '') ?>">
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i><span><?= html_escape($label) ?></span>
            </button>
        <?php elseif ($type === 'review'): ?>
            <a class="btn-icon record-action record-action-review" title="<?= html_escape($label) ?>"
                aria-label="<?= html_escape($label) ?>" target="_blank" rel="noopener" href="<?= site_url($button['url']) ?>">
                <i class="fa-solid fa-file-circle-check" aria-hidden="true"></i><span><?= html_escape($label) ?></span>
            </a>
        <?php elseif ($type === 'print'): ?>
            <a class="btn-icon record-action record-action-print" href="<?= site_url($button['url']) ?>" target="_blank"
                rel="noopener" title="Print controlled file" aria-label="Print controlled file">
                <i class="fa-solid fa-print" aria-hidden="true"></i><span>Print</span>
            </a>
        <?php elseif ($type === 'download'): ?>
            <a class="btn-icon record-action record-action-download" title="<?= html_escape($label) ?>"
                aria-label="<?= html_escape($label) ?>" href="<?= site_url($button['url']) ?>">
                <i class="fa-solid fa-download" aria-hidden="true"></i><span><?= html_escape($label) ?></span>
            </a>
        <?php elseif ($type === 'action'): ?>
            <?php
            $intent = in_array($button['decision'] ?? '', ['rejected', 'returned'], TRUE) ||
                !empty($button['disposal']) ||
                in_array(strtolower((string) $label), ['cancel', 'deactivate', 'remove', 'delete', 'reject', 'return'], TRUE)
                ? 'danger' : 'primary';
            ?>
            <button type="button" class="btn-icon record-action record-action-<?= $intent ?> js-action"
                title="<?= html_escape($label) ?>" aria-label="<?= html_escape($label) ?>"
                data-url="<?= site_url($button['url']) ?>" data-id="<?= (int) $dt_row['id'] ?>"
                data-decision="<?= html_escape($button['decision'] ?? '') ?>"
                data-disposal="<?= !empty($button['disposal']) ? 'yes' : 'no' ?>" data-title="<?= html_escape($label) ?>"
                data-description="<?= html_escape($button['description'] ?? '') ?>">
                <i class="<?= html_escape($button['icon'] ?? 'fa-solid fa-check') ?>" aria-hidden="true"></i>
                <span><?= html_escape($label) ?></span>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
