# ISSUE 018 — Backup setup says client missing when mysqldump works from PATH

**Severity:** Medium (installation feedback)  
**Type:** Confirmed PATH detection mismatch  
**Status:** Proposed fix only; NOT applied

## Evidence
- Backup_service::binary() can return the bare executable name mysqldump or mysql if it is on PATH.
- Backup_service::configuration() checks is_file($this->binary(...)), which returns FALSE for a command name that is found only through PATH.
- Result: Backup & Restore may say Setup required even when tools/backup.php manual executes correctly.

## Copyable fix
In Backup_service::configuration(), replace the dump/mysql status fields with:

~~~php
'dump'=>$this->commandAvailable($this->binary('PK_MYSQLDUMP','mysqldump')),
'mysql'=>$this->commandAvailable($this->binary('PK_MYSQL','mysql'))
~~~

Add the following helper to Backup_service:

~~~php
private function commandAvailable(string $command): bool
{
    if (is_file($command)) return TRUE;
    if (strpos($command,'/')!==FALSE ||
        strpos($command,chr(92))!==FALSE) return FALSE;
    foreach (explode(PATH_SEPARATOR,(string)getenv('PATH')) as $folder) {
        if ($folder==='') continue;
        $candidate=rtrim($folder,'/'.chr(92)).
            DIRECTORY_SEPARATOR.$command;
        if (is_file($candidate)) return TRUE;
        if (DIRECTORY_SEPARATOR===chr(92) &&
            is_file($candidate.'.exe')) return TRUE;
    }
    return FALSE;
}
~~~

## Verify
Unset PK_MYSQL and PK_MYSQLDUMP while exposing MariaDB client binaries on PATH. The setup indicator should show clients available and CLI backup/verify succeed. Repeat using explicit C:/xampp/mysql/bin/*.exe variables and invalid paths. The UI status is advisory; actual signed ZIP creation remains authoritative.
