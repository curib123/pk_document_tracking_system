# Issue 006: Backup ZIP accepts Windows-sensitive archive paths

Severity: HIGH (archive hardening) | Status: Confirmed incomplete path validation | Implemented: NO

Affected: application/services/backup/backup_service.php::safeEntry().

safeEntry() rejects dot-dot paths and backslashes but currently permits NTFS alternate-data-stream colon, Windows reserved device names, trailing dots/spaces and empty path segments. Backup imports have HMAC protection, but invalid paths should still fail before extraction.

## Copyable replacement of safeEntry()

~~~php
private static function safeEntry($name): bool
{
    if (!is_string($name) || $name === '' ||
        strpos($name, chr(92)) !== FALSE || $name[0] === '/' ||
        preg_match('/[<>:"|?*]/', $name) ||
        preg_match('/[[:cntrl:]]/', $name)) {
        return FALSE;
    }
    foreach (explode('/', $name) as $component) {
        if ($component === '' || $component === '.' ||
            $component === '..' ||
            preg_match('/[. ]$/', $component) ||
            preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])([.].*)?$/i',
                $component)) {
            return FALSE;
        }
    }
    return in_array($name, ['database.sql','manifest.json','manifest.sig'], TRUE) ||
        strpos($name, 'files/') === 0;
}
~~~

## Verify
Reject samples x:stream, ../, files//storage, files/CON.txt and files/a.; accept files/storage/documents/abcdef. Test actual signed ZIP verification on PHP 8.0 and Windows before permitting imports. Do not extract files until both signature and entries pass validation.
