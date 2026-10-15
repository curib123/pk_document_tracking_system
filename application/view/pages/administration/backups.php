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
        'buttons' => [['type' => 'download', 'url' => 'admin/backups/download/' . rawurlencode($item['name'])]]
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
                <form method="post" action="<?= site_url('admin/backups/cleanup') ?>"
                    data-confirm="Delete backup and log archives older than three months?">
                    <input type="hidden" name="confirmed" value="no">
                    <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
                    <button class="btn btn-light" type="submit" <?= $backup_error ? 'disabled' : '' ?>>Clean up</button>
                </form>
                <form method="post" action="<?= site_url('admin/backups/create') ?>"
                    data-confirm="Create an encrypted backup of the current system?">
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
        <h2 class="backup-section-title" id="backupArchiveTitle">Available backups</h2>
        <?php $this->load->view('reusable_components/reusable_datatable', compact(
            'dt_path', 'dt_columns', 'dt_rows', 'dt_q', 'dt_filter', 'dt_page', 'dt_limit',
            'dt_total', 'dt_sort', 'dt_dir', 'dt_sortable', 'dt_filters', 'dt_filter_values'
        )); ?>
    </section>
    <?php if ($canWrite): ?>
        <div class="backup-tools">
            <details class="workspace-card backup-tool">
                <summary>Import backup</summary>
                <form method="post" enctype="multipart/form-data" action="<?= site_url('admin/backups/import') ?>"
                    data-confirm="Verify and store this backup ZIP?">
                    <label class="form-label" for="backupZip">Backup ZIP</label>
                    <input class="form-control" type="file" name="backup_zip" id="backupZip" accept=".zip,application/zip" required>
                    <input type="hidden" name="confirmed" value="no">
                    <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
                    <button class="btn btn-primary" type="submit" <?= !$ready ? 'disabled' : '' ?>>Import backup</button>
                </form>
            </details>
            <details class="workspace-card backup-tool">
                <summary>Restore system data</summary>
                <p class="backup-warning">Replaces live records and private files. A recovery backup is created first.</p>
                <form method="post" action="<?= site_url('admin/backups/restore') ?>"
                    data-confirm="Replace the live database and private data with this backup?">
                    <div>
                        <label for="restoreArchive" class="form-label">Backup to restore</label>
                        <select class="form-select" id="restoreArchive" name="backup_name" required>
                            <option value="">Select backup</option>
                            <?php foreach ($backup_items as $item): ?>
                                <option value="<?= html_escape($item['name']) ?>"><?= html_escape($item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="restoreAdminPassword" class="form-label">Your password</label>
                        <input class="form-control" id="restoreAdminPassword" name="admin_password" type="password"
                            autocomplete="current-password" required>
                    </div>
                    <div>
                        <label for="restorePhrase" class="form-label">Type RESTORE to confirm</label>
                        <input class="form-control" id="restorePhrase" name="confirm_phrase" autocomplete="off" pattern="RESTORE" required>
                    </div>
                    <input type="hidden" name="confirmed" value="no">
                    <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfValue ?>">
                    <button class="btn btn-danger" type="submit" <?= !$ready || !$backup_items ? 'disabled' : '' ?>>Restore selected backup</button>
                </form>
            </details>
        </div>
    <?php endif; ?>
</div>
