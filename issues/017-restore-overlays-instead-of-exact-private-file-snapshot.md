# ISSUE 017 — Restoring an older ZIP leaves newer orphaned private files

**Severity:** High (data-integrity decision)  
**Type:** Confirmed intentional limitation; destructive exact cleanup NOT implemented  
**Status:** Manual verification guide only

## Evidence
- Backup_service::restore() verifies the ZIP, imports the SQL and calls copyDirectory() over the current private storage tree.
- copyDirectory() overwrites matching files but **does not delete** private files created after the snapshot.
- The database can therefore refer to an older set of files while newer bytes remain unreferenced on disk.

## Copyable read-only orphan report for an OFFLINE TEST RESTORE
In a disposable CodeIgniter test instance with a valid $db and $root:

~~~php
$directory = $root.'/storage/documents';
$files = $db->select('storage_name')->from('files')->get()->result_array();
$known = array_fill_keys(array_column($files,'storage_name'), TRUE);
$orphaned = [];
foreach (new DirectoryIterator($directory) as $entry) {
    if (!$entry->isFile() || $entry->isLink()) continue;
    if (!isset($known[$entry->getFilename()])) {
        $orphaned[] = $entry->getFilename();
    }
}
file_put_contents(
    $root.'/storage/restore-orphans.json',
    json_encode($orphaned,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
);
~~~

Do **not** automatically unlink reported files. Approved reconciliation should move them to a protected quarantine only after independent backup verification. A true exact restore requires an atomic staging directory swap and separate rollback of both DB and filesystem.

## Verify
Take a backup, add a new controlled file and record in a test installation, then restore the older archive. Confirm the new DB row is gone, its file is listed as orphaned and previously approved files still pass fingerprint checks. Review disposal and audit retention before any cleanup.
