# ISSUE 016 — 5:00 PM backup is not active until installed on the XAMPP host

**Severity:** High (deployment prerequisite)  
**Type:** Operational state cannot be verified from GitHub; code is present  
**Status:** Host action required; NOT applied

## Evidence
- tools/backup.php implements a CLI backup command.
- tools/install_backup_schedule.ps1 registers a Windows Task Scheduler trigger at 5:00 PM local machine time.
- A GitHub merge or XAMPP code copy **does not create the actual scheduled task**. A laptop/office server that is off at 5 PM cannot run it at the requested time.
- Administration → Backup & Restore shows an intended schedule but does not prove the job exists on your PC.

## Copyable Windows setup commands — run ON THE ACTUAL SERVER
Configure DB_*, PK_BACKUP_PASSWORD, PK_BACKUP_SIGNING_KEY and optional PK_BACKUP_DIR securely first (Machine-level for a SYSTEM scheduled task). Then, in elevated PowerShell:

~~~powershell
Get-TimeZone
Set-Location "C:/xampp/htdocs/pk_document_tracking_system"
powershell -ExecutionPolicy Bypass -File "./tools/install_backup_schedule.ps1" -ProjectRoot "C:/xampp/htdocs/pk_document_tracking_system" -PhpExe "C:/xampp/php/php.exe"
Get-ScheduledTask -TaskName "PKDTS-Daily-Backup-5PM"
Start-ScheduledTask -TaskName "PKDTS-Daily-Backup-5PM"
Get-ScheduledTaskInfo -TaskName "PKDTS-Daily-Backup-5PM"
~~~

Adjust paths to your actual directory. Do not paste passwords into the Git repo, command arguments or support screenshots. Windows must use a UTC+08:00 timezone (Asia/Manila equivalent). MySQL and Windows Task Scheduler must be running.

## Verify
The task must have a successful LastTaskResult. A new encrypted Automatic ZIP should appear under the private archive folder. Verify the ZIP and rehearse a full restore in a cloned installation. Check the next day's 5 PM run, failure events and disk space; keep at least one off-site copy. A missed backup should raise an operator alert rather than silently relying on page visits.
