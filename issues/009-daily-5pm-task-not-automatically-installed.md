# Issue 009: Daily 5 PM backup is inactive until scheduled on the real host

Severity: HIGH (operations) | Status: Confirmed deployment prerequisite | Implemented in XAMPP: NOT VERIFIED

Affected: tools/install_backup_schedule.ps1; tools/backup.php; docs/backup-restore.md.

PR #34 merged the scheduler implementation, but a GitHub merge cannot register Windows Task Scheduler jobs on the office PC. If the task has not been installed (or the machine is off), no 5 PM backup will occur.

## Copyable Windows activation commands

Run in an elevated PowerShell window on the Windows PC hosting XAMPP. Adjust the example path.

~~~powershell
Get-TimeZone
cd C:/xampp/htdocs/pk_document_tracking_system

powershell -ExecutionPolicy Bypass -File ./tools/install_backup_schedule.ps1 -ProjectRoot "C:/xampp/htdocs/pk_document_tracking_system" -PhpExe "C:/xampp/php/php.exe"

Get-ScheduledTask -TaskName "PKDTS-Daily-Backup-5PM"
Start-ScheduledTask -TaskName "PKDTS-Daily-Backup-5PM"
Get-ScheduledTaskInfo -TaskName "PKDTS-Daily-Backup-5PM"
~~~

Configure the Machine-scoped DB_* variables, PK_BACKUP_PASSWORD, PK_BACKUP_SIGNING_KEY and optional PK_BACKUP_DIR before activation. The host must have a UTC+8 timezone. NEVER commit passwords/keys to Git or paste them into issue files.

## Verify
Ensure LastTaskResult is successful. Confirm the next scheduled run creates an Automatic ZIP shown in Backup & Restore. Download and verify it. Test separately the behavior when the machine is off at 5 PM; StartWhenAvailable must launch the missed task when Windows next starts.
