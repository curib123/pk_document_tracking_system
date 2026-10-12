# Issue 004: Restore overlays files but does not recreate the exact snapshot

Severity: HIGH (consistency) | Status: Confirmed, current behavior is intentional for safety | Implemented: NO

Affected: application/services/backup/backup_service.php::restore() and copyDirectory().

After restoring an older database, copyDirectory() overwrites files from the ZIP but leaves private files created later. Those files can become orphaned without matching restored metadata. Automatic deletion is unsafe without a retention decision.

## Copyable, read-only verification utility

Place the following in a NEW internal CLI diagnostic after loading a CodeIgniter DB connection and setting $root. This lists unreferenced controlled files; it does not delete anything.

~~~php
$storage = $root.'/storage/documents';
$rows = $db->query('SELECT storage_name FROM files')->result_array();
$known = array_fill_keys(array_column($rows,'storage_name'), TRUE);
$orphaned = [];
foreach (new DirectoryIterator($storage) as $entry) {
    if ($entry->isDot() || !$entry->isFile() || $entry->isLink()) continue;
    if (!isset($known[$entry->getFilename()])) {
        $orphaned[] = $entry->getFilename();
    }
}
file_put_contents(
    $root.'/storage/orphaned-after-restore.json',
    json_encode($orphaned, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
);
~~~

A true exact restore requires staged directories, atomic rename/swap, checksum validation, and the ability to roll back both DB and private directories. That is a separate change requiring extensive integration testing. Do not turn this diagnostic into an automatic delete operation.

## Verify
On a clone, create a new file after a backup, restore the older ZIP, confirm its database metadata is absent and this diagnostic reports the orphan. Verify that all referenced historical files still download.
