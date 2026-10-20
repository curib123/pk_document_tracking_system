<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$backupTime = static fn($timestamp) => (new DateTimeImmutable('@' . (int) $timestamp))
    ->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y · g:i A');
$canWrite = isset($permissions['*']) || !empty($permissions['backups']['edit']);
$ready = $backup_config && !in_array(FALSE, $backup_config, TRUE) && $backup_error === '';
$csrfName = $this->security->get_csrf_token_name();
$csrfValue = $this->security->get_csrf_hash();
$dt_rows = [];
foreach ($backup_rows as $index => $item) {
    $dt_rows[] = [
        'id' => ($table['page'] - 1) * $table['limit'] + $index + 1,
        'cells' => ['name' => $item['name'], 'kind' => $item['kind'],
            'created_at' => $backupTime($item['created_at']), 'size' => number_format($item['size'] / 1048576, 2) . ' MB'],
        'buttons' => $canWrite ? [['type' => 'download', 'url' => 'admin/backups/download/' . rawurlencode($item['name'])]] : []
    ];
}
$dt_path = 'admin/backups';
$dt_columns = ['name' => 'Archive', 'kind' => 'Type', 'created_at' => 'Created', 'size' => 'Size'];
$dt_q = $table['q']; $dt_filter = ''; $dt_page = $table['page']; $dt_limit = $table['limit']; $dt_total = $total;
$dt_sort = $table['sort']; $dt_dir = strtolower($table['dir']); $dt_sortable = array_keys($dt_columns);
$dt_filters = ['type' => ['' => 'All types', 'Manual' => 'Manual', 'Automatic' => 'Automatic', 'Recovery' => 'Recovery']];
$dt_filter_values = ['type' => $table['type']];
?>
<div class="backup-workspace">
    <header class="backup-overview">
        <div>
            <h1>Backup &amp; Restore</h1>
            <p>Daily schedule: 5:00 PM · Asia/Manila</p>
            <?php if ($backup_last_auto): ?>
                <small>Last automatic backup: <?= html_escape($backupTime($backup_last_auto['created_at'])) ?></small>
            <?php endif; ?>
        </div>
        <?php if ($canWrite): ?>
            <div class="backup-toolbar">
                <button class="btn btn-light" type="button" data-bs-toggle="modal" data-bs-target="#backupImportModal">
                    <i class="fa-solid fa-file-import" aria-hidden="true"></i> Import backup
                </button>
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#backupRestoreModal">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restore system data
                </button>
                <form method="post" action="<?= site_url('admin/backups/cleanup') ?>"
                    data-confirm="Delete backup and log archives older than three months?" autocomplete="off">
                    <input type="hidden" name="confirmed" value="no">
                    <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
                    <button class="btn btn-light" type="submit" <?= $backup_error ? 'disabled' : '' ?>>Clean up</button>
                </form>
                <form method="post" action="<?= site_url('admin/backups/create') ?>"
                    data-confirm="Create an encrypted backup of the current system?" autocomplete="off">
                    <input type="hidden" name="confirmed" value="no">
                    <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
                    <button class="btn btn-primary" type="submit" <?= !$ready ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Backup now
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </header>
    <?php if ($backup_error): ?>
        <div class="backup-warning" role="alert"><?= html_escape($backup_error) ?></div>
    <?php elseif (!$ready): ?>
        <div class="backup-warning" role="status">Backup setup is incomplete. Contact your system administrator.</div>
    <?php endif; ?>
    <section aria-labelledby="backupArchiveTitle">
        <?php $this->load->view('reusable_components/reusable_datatable', compact(
            'dt_path', 'dt_columns', 'dt_rows', 'dt_q', 'dt_filter', 'dt_page', 'dt_limit',
            'dt_total', 'dt_sort', 'dt_dir', 'dt_sortable', 'dt_filters', 'dt_filter_values'
        )); ?>
    </section>
</div>
<?php if ($canWrite): ?>
    <div class="modal fade backup-modal" id="backupImportModal" tabindex="-1" aria-labelledby="backupImportTitle"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data" action="<?= site_url('admin/backups/import') ?>"
                    data-confirm="Verify and store this backup ZIP?" autocomplete="off">
                    <div class="modal-header">
                        <h2 class="modal-title fs-6" id="backupImportTitle">Import backup</h2>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="backupZip">Backup ZIP</label>
                        <input class="form-control" type="file" name="backup_zip" id="backupZip" accept=".zip,application/zip" required autocomplete="off">
                    </div>
                    <input type="hidden" name="confirmed" value="no">
                    <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit" <?= !$ready ? 'disabled' : '' ?>>Import backup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade backup-modal" id="backupRestoreModal" tabindex="-1" aria-labelledby="backupRestoreTitle"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form method="post" action="<?= site_url('admin/backups/restore') ?>"
                    data-confirm="Replace the live database and private data with this backup?" autocomplete="off">
                    <div class="modal-header">
                        <h2 class="modal-title fs-6" id="backupRestoreTitle">Restore system data</h2>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="backup-warning">Replaces live records and private files. A recovery backup is created first.</p>
                        <div class="backup-restore-fields">
                            <div class="backup-field-full">
                                <label for="restoreArchive" class="form-label">Backup to restore</label>
                                <select class="form-select" id="restoreArchive" name="backup_name" required autocomplete="off">
                                    <option value="">Select backup</option>
                                    <?php foreach ($backup_items as $item): ?>
                                        <option value="<?= html_escape($item['name']) ?>"><?= html_escape($item['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="restoreAdminPassword" class="form-label">Your password</label>
                                <input class="form-control" id="restoreAdminPassword" name="admin_password" type="password"
                                    autocomplete="off" required>
                            </div>
                            <div>
                                <label for="restorePhrase" class="form-label">Type RESTORE to confirm</label>
                                <input class="form-control" id="restorePhrase" name="confirm_phrase" autocomplete="off" pattern="RESTORE" required>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="confirmed" value="no">
                    <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger" type="submit" <?= !$ready || !$backup_items ? 'disabled' : '' ?>>Restore selected backup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
