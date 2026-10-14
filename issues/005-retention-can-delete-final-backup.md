# ISSUE 005 — Manual cleanup can delete the last recoverable ZIP

**Severity:** Critical (backup/recovery availability)  
**Type:** Confirmed retention policy gap  
**Status:** Proposed fix only; NOT applied

## Evidence
- Retention_service::cleanup() deletes any managed backup ZIP whose name dates back more than three months.
- Backups::cleanup() exposes this action manually. If no recent backups exist (disabled scheduler, long machine outage or migration), cleanup can erase every old archive, including the only recovery backup. Archives are not verified before deletion.
- A successful Backup_service::backup() usually creates a new archive before cleanup, but MANUAL cleanup does not.

## Copyable minimum safety guard
In application/services/backup/retention_service.php, inside cleanup() after computing $directories and BEFORE iterating them, calculate protected archives:

~~~php
$protectedArchives = [];
$names = glob($this->backups.'/pk-dts-*.zip') ?: [];
$latestByClass = [];
foreach ($names as $path) {
    if (!is_file($path) || is_link($path)) continue;
    $name = basename($path);
    if (!$this->managedDate('backups', $name)) continue;
    $kind = strpos($name,'pk-dts-recovery-')===0
        ? 'recovery' : 'regular';
    $stamp = (int)filemtime($path);
    if (!isset($latestByClass[$kind]) ||
        $stamp > $latestByClass[$kind]['stamp']) {
        $latestByClass[$kind] = ['name'=>$name,'stamp'=>$stamp];
    }
}
foreach ($latestByClass as $latest) {
    $protectedArchives[$latest['name']] = TRUE;
}
~~~

Inside the existing foreach ($names as $name) in cleanup(), immediately after the managedDate check, insert:

~~~php
if ($kind === 'backups' && isset($protectedArchives[$name])) {
    continue;
}
~~~

This protects at least the most recent regular ZIP and recovery ZIP. It does NOT prove either is valid. Before any automatic deletion, a stronger implementation should verify that another good signed/encrypted backup and off-site copy exist. Protecting audit JSON may also be subject to your organization's statutory retention policy.

## Verify
In a disposable storage directory create only one old manual ZIP and one old recovery ZIP; run cleanup at a later simulated date: both must remain. Add more old and new ZIPs; older duplicates can be pruned but the newest per class survives. Test symlinks, file permission errors, no backup files and zero failed cleanup reports.
