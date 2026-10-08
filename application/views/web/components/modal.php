<?php
/**
 * Shared dialog for forms, confirmations, and alerts.
 * All action URLs and HTML body fragments originate from trusted PHP views.
 */
$modalId = (string) ($modalId ?? 'pk-dialog');
$modalTitle = (string) ($modalTitle ?? 'Notice');
$modalTone = in_array(($modalTone ?? 'info'), ['danger','warning','info','success'], true) ? $modalTone : 'info';
$modalMessage = (string) ($modalMessage ?? '');
$modalContentHtml = (string) ($modalContentHtml ?? '');
$modalAction = isset($modalAction) && is_string($modalAction) && $modalAction !== '' ? $modalAction : null;
$modalFields = is_array($modalFields ?? null) ? $modalFields : [];
$modalSubmit = (string) ($modalSubmit ?? 'Save');
$modalBack = (string) ($modalBack ?? '');
$modalAutoOpen = !empty($modalAutoOpen);
$modalForm = $modalAction !== null;
$modalTitleId = $modalId . '-title';
?>
<div class="modal fade pk-page-modal" id="<?= ui_escape($modalId) ?>" tabindex="-1"
     role="dialog" aria-modal="true" aria-labelledby="<?= ui_escape($modalTitleId) ?>"
     data-pk-auto-open="<?= $modalAutoOpen ? 'true' : 'false' ?>"
     <?= $modalBack !== '' ? 'data-pk-back-url="' . ui_escape($modalBack) . '"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <?php if ($modalForm): ?>
                <form method="post" action="<?= ui_escape($modalAction) ?>" data-pk-native-form>
                    <?php foreach ($modalFields as $key => $value): ?>
                        <input type="hidden" name="<?= ui_escape($key) ?>" value="<?= ui_escape($value) ?>">
                    <?php endforeach; ?>
            <?php endif; ?>
            <div class="modal-header">
                <div class="pk-modal-symbol <?= $modalTone === 'danger' ? 'is-danger' : '' ?>">
                    <?= pk_web_icon($modalTone === 'danger' ? 'warning' : ($modalTone === 'success' ? 'success' : 'info')) ?>
                </div>
                <div class="flex-grow-1">
                    <h2 class="modal-title" id="<?= ui_escape($modalTitleId) ?>"><?= ui_escape($modalTitle) ?></h2>
                </div>
                <?php if ($modalBack !== ''): ?>
                    <a class="btn-close ms-2" aria-label="Close dialog" href="<?= ui_escape($modalBack) ?>"></a>
                <?php else: ?>
                    <button class="btn-close ms-2" type="button" data-bs-dismiss="modal" aria-label="Close dialog"></button>
                <?php endif; ?>
            </div>
            <div class="modal-body">
                <?php if ($modalMessage !== ''): ?>
                    <p><?= ui_escape($modalMessage) ?></p>
                <?php endif; ?>
                <?= $modalContentHtml /* Only rendered, HTML-escaped trusted PHP partials. */ ?>
            </div>
            <div class="modal-footer">
                <?php if ($modalBack !== ''): ?>
                    <a class="btn btn-outline-secondary" href="<?= ui_escape($modalBack) ?>">Cancel</a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <?php endif; ?>
                <?php if ($modalForm): ?>
                    <button type="submit" class="btn <?= $modalTone === 'danger' ? 'btn-danger' : 'btn-primary' ?>">
                        <?= ui_escape($modalSubmit) ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($modalForm): ?></form><?php endif; ?>
        </div>
    </div>
</div>
<?php
unset($modalId, $modalTitle, $modalTone, $modalMessage, $modalContentHtml, $modalAction, $modalFields, $modalSubmit, $modalBack, $modalAutoOpen, $modalForm, $modalTitleId);
?>
