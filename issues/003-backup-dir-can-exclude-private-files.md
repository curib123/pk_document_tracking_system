# Issue 003: A custom backup destination can cause incomplete backups

Severity: HIGH (recovery completeness) | Status: Confirmed configuration defect | Implemented: NO

Affected: application/services/backup/backup_service.php.

The backup constructor rejects directories under public/, but accepts a PK_BACKUP_DIR that equals storage/ or storage/documents/. sourceFiles() skips everything inside the backup directory. In these configurations the ZIP may exclude controlled documents and audit JSON even while reporting success.

## Copyable fix

Inside Backup_service::__construct(), after resolving $real and checking the public path, before assigning $this->directory, insert:

~~~php
foreach ([
    $this->root.'/storage/documents',
    $this->root.'/storage/audit',
    $this->root.'/storage/logs'
] as $protected) {
    $source = realpath($protected);
    if ($source && (
        $this->inside($real, $source) ||
        $this->inside($source, $real)
    )) {
        throw new DomainException(
            'Backup destination must be separate from live private data.'
        );
    }
}
~~~

Prefer the default storage/backups or a separate encrypted backup drive.

## Verify
Try PK_BACKUP_DIR=storage, PK_BACKUP_DIR=storage/documents and PK_BACKUP_DIR=storage/audit; each must be rejected. Try the default storage/backups and an external drive; both should work. Inspect the signed manifest and verify all expected controlled files and audit logs exist in the ZIP.
