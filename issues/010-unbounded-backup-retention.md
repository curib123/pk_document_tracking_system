# Issue 010: Unlimited complete daily ZIP archives can fill backup storage

Severity: MEDIUM-HIGH (availability) | Status: Resolved with three-month retention | Implemented: YES

Affected: application/services/backup/backup_service.php::backup(), list_backups().

Every backup includes database, application, documents and audit data. Previously, daily archives accumulated forever. A full disk could prevent further reliable backups and system uploads.

Implemented policy: generated Automatic, Manual, imported and Recovery ZIPs, daily audit JSON files and daily application logs older than three calendar months in Asia/Manila are removed. Cleanup runs after a successful new backup and once daily on application use. It preserves the cutoff boundary, clamps month ends, excludes documents/unmanaged files and coordinates backup/restore and audit locks. See `docs/backup-restore.md` and run `php tests/backup_retention.php`.

## Historical alternative: retain 30 newest Automatic ZIP files

The example below is superseded by the implemented three-calendar-month policy.

Replace Backup_service::backup() with:

~~~php
public function backup($kind='manual'): array
{
    if (!in_array($kind,['manual','auto','recovery'],TRUE)) {
        throw new DomainException('Invalid backup type.');
    }
    $lock=$this->lock();
    try {
        $result=$this->createLocked($kind);
        if ($kind==='auto') $this->pruneAutomatic(30);
        return $result;
    } finally {
        $this->unlock($lock);
    }
}
~~~

Add a private method:

~~~php
private function pruneAutomatic(int $keep): void
{
    $files=glob($this->directory.'/pk-dts-auto-*.zip')?:[];
    $files=array_values(array_filter($files,static function($file){
        return is_file($file) && !is_link($file);
    }));
    usort($files,static function($a,$b){
        return filemtime($b)<=>filemtime($a);
    });
    foreach (array_slice($files,max(1,$keep)) as $file) {
        if (!@unlink($file)) {
            error_log('Could not prune an old automatic ZIP.');
        }
    }
}
~~~

That historical example preserved Manual and Recovery ZIPs. The implemented three-month policy covers all generated ZIP types. Keep separate encrypted off-site monthly archives according to office retention policy.

## Verify
With 35 synthetic Automatic ZIP files in a disposable backup directory, the next successful automatic run leaves 30. A failed new backup must not prune anything. Manual/Recovery ZIP counts should remain unchanged.
