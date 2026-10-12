# Issue 019: Database snapshot and file snapshot can represent different moments

Severity: MEDIUM-HIGH (recovery integrity) | Status: Confirmed design limitation | Implemented: NO

Affected: application/services/backup/backup_service.php::createLocked(), dumpDatabase(), sourceFiles().

The database is dumped with mysqldump --single-transaction, but private document files are enumerated and copied into ZIP afterward. If a Softcopy upload/revision or file cleanup occurs between these steps, restored metadata and file bytes may not match one point in time. Scheduling at 5 PM reduces but does not eliminate this race.

## Copyable safe operational workaround

Do not run interactive writes during the 5 PM backup window. On your server, temporarily put the application into a planned maintenance window (after staff leave), then run the existing CLI backup:

~~~powershell
Set-Location "C:/xampp/htdocs/pk_document_tracking_system"
& "C:/xampp/php/php.exe" tools/backup.php auto
& "C:/xampp/php/php.exe" tools/backup.php list
~~~

To automate true point-in-time snapshots without interrupting users, design a read-consistent file manifest in the same transaction, freeze/retain immutable file blobs during backup, and only release the snapshot after ZIP verification. This needs architecture-specific integration work, not an untested one-line change.

## Copyable pre-restore consistency test

After extracting to a disposable staging tree and importing into a clone, compare hashes stored in files.fingerprint with actual storage/document bytes:

~~~php
foreach ($db->query('SELECT storage_name,fingerprint FROM files')
    ->result_array() as $row) {
    $path = $root.'/storage/documents/'.$row['storage_name'];
    if (!is_file($path) ||
        !hash_equals((string)$row['fingerprint'],hash_file('sha256',$path))) {
        error_log('Backup document mismatch: '.$row['storage_name']);
    }
}
~~~

## Verify
Run automated backup simultaneously with a test Softcopy upload/approval. Restore on a clone and check every files row has a matching readable file with the expected SHA-256 fingerprint. Review approval and revision history timestamps for consistency.
