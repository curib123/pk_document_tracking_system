# Issue 008: Backup setup indicator can falsely report missing MariaDB clients

Severity: MEDIUM (setup) | Status: Confirmed | Implemented: NO

Affected: application/services/backup/backup_service.php::configuration() and binary().

binary() can return a program name such as mysqldump if it is available via PATH. configuration() currently calls is_file() on the program name, which can return FALSE even when proc_open can execute it. The administrator sees Setup required on a working host.

## Copyable fix

Replace the dump/mysql fields in Backup_service::configuration() with:

~~~php
'dump'=>$this->commandAvailable($this->binary('PK_MYSQLDUMP','mysqldump')),
'mysql'=>$this->commandAvailable($this->binary('PK_MYSQL','mysql'))
~~~

Add this method to Backup_service:

~~~php
private function commandAvailable(string $command): bool
{
    if (is_file($command)) return TRUE;
    if (strpos($command, '/') !== FALSE ||
        strpos($command, chr(92)) !== FALSE) return FALSE;
    foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $folder) {
        if ($folder === '') continue;
        $candidate = rtrim($folder, '/'.chr(92)).
            DIRECTORY_SEPARATOR.$command;
        if (is_file($candidate)) return TRUE;
        if (DIRECTORY_SEPARATOR === chr(92) &&
            is_file($candidate.'.exe')) return TRUE;
    }
    return FALSE;
}
~~~

## Verify
On a host where mysql and mysqldump are in PATH but no full PK_MYSQL/PK_MYSQLDUMP paths are set, CLI backup and setup status should both succeed. Test explicit XAMPP .exe paths and invalid paths too.
