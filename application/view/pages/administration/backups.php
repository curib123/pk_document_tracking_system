<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$backupTime=static function($timestamp) {
    return (new DateTimeImmutable('@'.(int)$timestamp))
        ->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y · g:i A');
};
?>
<div class="backup-workspace">
  <div class="backup-overview">
    <div class="backup-intro">
      <span class="backup-emblem" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
      <div>
        <h1>Backup &amp; Restore</h1>
        <p>Protected ZIP snapshots of database records, controlled documents, audit history and application files.</p>
      </div>
    </div>
    <div class="backup-time">
      <i class="fa-regular fa-clock" aria-hidden="true"></i>
      <div><strong>5:00 PM daily</strong><small>Asia/Manila · Server schedule required</small></div>
    </div>
  </div>
  <div class="backup-stats">
    <div class="backup-stat">
      <span class="backup-stat-icon" aria-hidden="true"><i class="fa-solid fa-box-archive"></i></span>
      <div><small>Stored archives</small><strong><?= count($backup_items) ?></strong></div>
    </div>
    <div class="backup-stat">
      <span class="backup-stat-icon" aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></span>
      <div><small>Last automatic backup</small>
        <strong><?= $backup_last_auto?html_escape($backupTime($backup_last_auto['created_at'])):'Not recorded' ?></strong>
      </div>
    </div>
    <div class="backup-stat">
      <span class="backup-stat-icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <div><small>Backup protection</small>
        <strong><?= count(array_filter($backup_config))===count($backup_config)?'Configured':'Setup required' ?></strong>
      </div>
    </div>
  </div>
  <?php if (count(array_filter($backup_config))!==count($backup_config)): ?>
  <div class="backup-setup-note" role="status">
    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
    <div>Backup setup is incomplete. Enable PHP ZIP, configure the database client binaries,
    and set <code>PK_BACKUP_PASSWORD</code> and <code>PK_BACKUP_SIGNING_KEY</code> on the server.
    Use <code>docs/backup-restore.md</code> for Windows Task Scheduler installation.</div>
  </div>
  <?php endif; ?>
  <div class="backup-actions">
    <section class="workspace-card backup-task-card" aria-labelledby="backupCreateTitle">
      <div class="backup-task-head">
        <span class="backup-task-icon" aria-hidden="true"><i class="fa-solid fa-file-zipper"></i></span>
        <div><h2 id="backupCreateTitle">Create backup</h2>
          <p>Generate a signed, encrypted ZIP now.</p></div>
      </div>
      <form method="post" action="<?= site_url('admin/backups/create') ?>"
            data-confirm="Create a complete encrypted backup of the current system?">
        <input type="hidden" name="confirmed" value="no">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
               value="<?= $this->security->get_csrf_hash() ?>">
        <button class="btn btn-primary backup-full-button" type="submit">
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Backup now
        </button>
      </form>
    </section>
    <section class="workspace-card backup-task-card" aria-labelledby="backupImportTitle">
      <div class="backup-task-head">
        <span class="backup-task-icon" aria-hidden="true"><i class="fa-solid fa-file-arrow-up"></i></span>
        <div><h2 id="backupImportTitle">Import backup ZIP</h2>
          <p>Upload a previously generated, signed PK Document Control backup.</p></div>
      </div>
      <form method="post" enctype="multipart/form-data"
            action="<?= site_url('admin/backups/import') ?>"
            data-confirm="Verify and store this backup ZIP? Existing records will not change.">
        <label class="form-label" for="backupZip">Backup ZIP</label>
        <input class="form-control mb-3" type="file" name="backup_zip" id="backupZip"
               accept=".zip,application/zip" required>
        <input type="hidden" name="confirmed" value="no">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
               value="<?= $this->security->get_csrf_hash() ?>">
        <button class="btn btn-light backup-full-button" type="submit">
          <i class="fa-solid fa-file-import" aria-hidden="true"></i> Import backup
        </button>
      </form>
    </section>
  </div>
  <section class="workspace-card backup-register" aria-labelledby="backupArchiveTitle">
    <div class="workspace-card-header backup-register-header">
      <div><h2 id="backupArchiveTitle">Available backups</h2>
        <p>Access is granted when any active role allows the action.</p></div>
    </div>
    <?php if (!$backup_items): ?>
      <div class="empty-state" role="status"><i class="fa-regular fa-folder-open" aria-hidden="true"></i>
        <strong>No backups available</strong></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table backup-table">
        <thead><tr><th scope="col">Archive</th><th scope="col">Type</th>
          <th scope="col">Created</th><th scope="col">Size</th><th scope="col">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($backup_items as $item): ?>
          <tr><td class="backup-filename">
            <i class="fa-solid fa-file-zipper" aria-hidden="true"></i>
            <?= html_escape($item['name']) ?></td>
            <td><span class="backup-kind"><?= html_escape($item['kind']) ?></span></td>
            <td><?= html_escape($backupTime($item['created_at'])) ?></td>
            <td><?= number_format($item['size']/1024/1024,2) ?> MB</td>
            <td><a class="btn btn-sm btn-outline-primary"
                href="<?= site_url('admin/backups/download/'.rawurlencode($item['name'])) ?>">
                <i class="fa-solid fa-download" aria-hidden="true"></i> Download
            </a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
  <section class="workspace-card backup-restore-card" aria-labelledby="restoreTitle">
    <div class="backup-task-head">
      <span class="backup-task-icon backup-restore-icon" aria-hidden="true"><i class="fa-solid fa-rotate-left"></i></span>
      <div><h2 id="restoreTitle">Restore system data</h2>
        <p>Restores the database and private document/audit data. Source files in the ZIP are intended for offline application recovery.</p></div>
    </div>
    <div class="backup-warning">
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
      <span>Restoring replaces live database records. A recovery ZIP is created first.
      Use a maintenance window and verify the backup before proceeding.</span>
    </div>
    <form method="post" action="<?= site_url('admin/backups/restore') ?>"
        data-confirm="This permanently replaces the live database and private data. Continue?">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="restoreArchive" class="form-label">Backup to restore</label>
          <select class="form-select" id="restoreArchive" name="backup_name" required>
            <option value="">Select backup</option>
            <?php foreach ($backup_items as $item): ?>
            <option value="<?= html_escape($item['name']) ?>"><?= html_escape($item['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label for="restoreAdminPassword" class="form-label">Your password</label>
          <input class="form-control" id="restoreAdminPassword" name="admin_password" type="password"
            autocomplete="current-password" required>
        </div>
        <div class="col-md-6">
          <label for="restorePhrase" class="form-label">Type RESTORE to confirm</label>
          <input class="form-control" id="restorePhrase" name="confirm_phrase" type="text"
             autocomplete="off" pattern="RESTORE" required>
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <button class="btn btn-danger backup-full-button" type="submit" <?= !$backup_items?'disabled':'' ?>>
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restore selected backup
          </button>
        </div>
      </div>
      <input type="hidden" name="confirmed" value="no">
      <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
             value="<?= $this->security->get_csrf_hash() ?>">
    </form>
  </section>
</div>
