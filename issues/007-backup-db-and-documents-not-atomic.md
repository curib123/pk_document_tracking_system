# ISSUE 007 — Database dump and private-file copy are not a point-in-time snapshot

**Severity:** High (disaster-recovery consistency)  
**Type:** Confirmed architectural limitation  
**Status:** Design and test guide only; NOT implemented

## Evidence
- Backup_service::createLocked() calls dumpDatabase() before sourceFiles()/ZipArchive copies files.
- --single-transaction makes InnoDB tables transactionally consistent with each other, but it does not freeze document uploads or files on disk.
- A document can be recorded/approved or removed between the SQL dump and byte-level file copy.

## Immediate safe operational procedure
Coordinate a maintenance window, stop users from uploading/approving files, and run the existing CLI backup on the trusted host:

~~~powershell
Set-Location "C:/xampp/htdocs/pk_document_tracking_system"
& "C:/xampp/php/php.exe" tools/backup.php manual
& "C:/xampp/php/php.exe" tools/backup.php list
~~~

If backups need to work DURING normal operation, implement a snapshot protocol: either filesystem/database snapshots on consistent storage, or immutable file content plus a database snapshot mapping whose hashes are checked after archive close. A one-line flock() inside Backup_service is **not sufficient** unless every upload/approval path acquires the same lock.

## Copyable consistency verification for an OFFLINE cloned restore
After loading the restored database through CodeIgniter and defining $root, validate current and historical files:

~~~php
$missing = [];
$rows = $db->select('storage_name,fingerprint')
    ->from('files')->get()->result_array();
foreach ($rows as $row) {
    $path = $root.'/storage/documents/'.$row['storage_name'];
    if (!is_file($path) ||
        !hash_equals((string)$row['fingerprint'],
            (string)hash_file('sha256',$path))) {
        $missing[] = $row['storage_name'];
    }
}
if ($missing) {
    throw new RuntimeException(
        'Restored database/files mismatch on '.
        count($missing).' stored documents.'
    );
}
~~~

## Verify
Take a backup during a controlled test revision upload, restore on a CLONE and run the consistency snippet. Compare every files.fingerprint and storage_name. Do not delete live controlled documents to simulate a failure. Require explicit acceptance criteria and separate integration tests before calling this architectural item fixed.
