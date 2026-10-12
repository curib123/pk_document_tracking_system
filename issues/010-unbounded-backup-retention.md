# Issue 010: Unlimited complete daily ZIP archives can fill backup storage

Severity: MEDIUM-HIGH (availability) | Status: Confirmed absence of retention policy | Implemented: NO

Affected: application/services/backup/backup_service.php::backup(), list_backups().

Every backup includes database, application, documents and audit data. Daily archives accumulate forever. A full disk can prevent further reliable backups and system uploads.

## Optional copyable policy: retain 30 newest Automatic ZIP files

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

Manual and recovery ZIPs must NOT be pruned automatically. Keep separate encrypted off-site monthly archives according to approved office retention policy.

## Verify
With 35 synthetic Automatic ZIP files in a disposable backup directory, the next successful automatic run leaves 30. A failed new backup must not prune anything. Manual/Recovery ZIP counts should remain unchanged.
