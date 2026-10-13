# Backup & Restore — PK Document Tracking System

The administrator-only Backup & Restore screen at /admin/backups creates, imports, downloads and restores encrypted ZIP snapshots. Automatic snapshots run at **5:00 PM Asia/Manila** when you register the daily operating-system task **on your XAMPP server**. A GitHub merge cannot register that task on your Windows PC.

## What the backup includes

- database.sql: complete MariaDB schema and current data, including workflows, permissions, users and document metadata.
- files/application and files/public: application code, configuration, UI and site assets.
- files/storage/documents: all private softcopy documents and controlled revision attachments.
- files/storage/audit, files/storage/logs and other non-transient private data.
- files/vendor (if present), project scripts, database templates and source files.
- Encrypted manifest and signature: individual SHA-256 checksums and HMAC for tamper detection.

Backups use AES-256 ZIP encryption and a separate HMAC signing key. Exclusions: old backup archives, .git, node_modules, temporary sessions/caches and .env secrets. **Save your DB_*, PK_BACKUP_* and PK_ENCRYPTION_KEY configuration separately in a password manager**. The ZIP includes sensitive employee, document and credential-hash data; do not share passwords or store it in a public folder.

## Required installation on Windows/XAMPP

1. Confirm PHP 8.0+ has zip, mysqli and fileinfo enabled, with ZipArchive AES-256 support and proc_open enabled.
2. Set Machine-scoped environment variables, available to both Apache and the scheduled task:
   - DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
   - PK_BACKUP_PASSWORD: strong random password (12+ characters)
   - PK_BACKUP_SIGNING_KEY: different random secret (32+ characters)
   - PK_BACKUP_DIR: optional absolute, private off-web path such as D:\PK-DTS-Backups (recommended separate drive)
   - PK_MYSQLDUMP and PK_MYSQL: optional full paths to mysqldump.exe and mysql.exe
3. Make the backup directory writable to Apache and the scheduled task account, and restart services after changing Machine environment variables.
4. Verify that the Windows time zone is UTC+08:00, such as Singapore Standard Time.
5. In **elevated PowerShell** from the project root, run these commands (adjust paths):

        Get-TimeZone
        powershell -ExecutionPolicy Bypass -File .\tools\install_backup_schedule.ps1 -ProjectRoot "C:\xampp\htdocs\pk_document_tracking_system" -PhpExe "C:\xampp\php\php.exe"
        Start-ScheduledTask -TaskName "PKDTS-Daily-Backup-5PM"
        Get-ScheduledTaskInfo -TaskName "PKDTS-Daily-Backup-5PM"

The task uses Windows Task Scheduler at 17:00 local time, including when the site is closed. The PC, MariaDB and scheduler must be running. Confirm a fresh Automatic ZIP in Backup & Restore and test a restore on a separate copy.

### Linux schedule

Use a scheduler with Asia/Manila timezone support and pass secrets securely through its environment (not CLI arguments):

        CRON_TZ=Asia/Manila
        0 17 * * * cd /srv/pk_document_tracking_system && /usr/bin/php tools/backup.php auto >> /var/log/pk-dts-backup.log 2>&1

## Manual backup

Use Administrator → Backup & Restore → Backup now, or on the server:

        php tools/backup.php manual
        php tools/backup.php list
        php tools/backup.php verify pk-dts-auto-YYYYMMDD-HHMMSS-xxxxxxxxxx.zip

The ZIP can be downloaded through the authenticated administrator interface only.

## Restore

**Use a maintenance window**. Database import can replace users, permissions, workflows and document records.

1. Create a new backup of the current data first. Import an older protected ZIP, if necessary; import only verifies and stores it.
2. On the Backup & Restore screen, choose the archive, enter the administrator password, type RESTORE, and confirm.
3. The restore service verifies the HMAC signature and every file checksum and path before touching live data. It creates a new complete recovery ZIP automatically.
4. The database and private document/audit data are restored. A restored account database can invalidate the current login; sign in using an account from the restored snapshot.
5. Browser restore does not overwrite running executable PHP or frontend source. The full source tree is included for **offline disaster recovery**: shut the website down, extract the trusted encrypted ZIP, copy the files/ application and public tree to the correct server directories, and reinstall dependencies as necessary.
6. Test that documents, workflows, permissions, and downloads match the restored backup.

CLI restore of database and private data:

        php tools/backup.php restore pk-dts-auto-YYYYMMDD-HHMMSS-xxxxxxxxxx.zip --confirm

If import or file restoration fails, the service attempts to reapply the pre-restore recovery snapshot. Since SQL DDL cannot be rolled back as a normal transaction, **test on a cloned installation before production use**. Existing files not present in the snapshot are kept rather than deleted to avoid destructive orphan cleanup.

If both restore and automatic recovery fail, the maintenance flag at storage/.restore-in-progress remains in place to block normal authenticated access. Recover using a trusted recovery ZIP from the server CLI, verify the database and private files, then clear the flag only after completing recovery. Never delete the maintenance flag just to force the site online before checking data integrity.

## Operational requirements

- Merging code alone **does not install the 5 PM task**; someone must run the Windows PowerShell scheduler setup on the server.
- Keep protected backups off-site or on a separate encrypted drive. One disk failure can destroy both production and local backups.
- Protect and retain your backup encryption password and signing key. Changing these means older backups cannot be restored without their original secrets.
- The system does not automatically delete archives. Monitor free disk space, set a tested off-site retention policy, and regularly practice recovery.
- Daily dump uses MariaDB single-transaction mode for transactional tables, but file uploads during backup can produce a mixed point-in-time snapshot. Avoid active document edits or transfers during the backup window.
- Large ZIP uploads require sufficient PHP upload_max_filesize and post_max_size; use CLI or a secured local transfer for archives exceeding browser limits.






Step 1 — Generate and save a signing key

In your current PowerShell window, run:

$keyBytes = New-Object byte[] 48
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()

try {
    $rng.GetBytes($keyBytes)
}
finally {
    $rng.Dispose()
}

$signingKey = [Convert]::ToBase64String($keyBytes).
    TrimEnd('=').
    Replace('+', '-').
    Replace('/', '_')

[Environment]::SetEnvironmentVariable(
    "PK_BACKUP_SIGNING_KEY",
    $signingKey,
    [EnvironmentVariableTarget]::Machine
)

$signingKey = $null
$keyBytes = $null

Write-Host "Signing key configuration attempted."

This generates a new, random signing key of 64 characters. It does not print the key.

If you receive “Requested registry access is not allowed,” close this window and reopen PowerShell using Run as administrator, then repeat Step 1.

Step 2 — Verify the signing key

Run:

$value = [Environment]::GetEnvironmentVariable(
    "PK_BACKUP_SIGNING_KEY",
    [EnvironmentVariableTarget]::Machine
)

Write-Host "Signing key configured:" (-not [string]::IsNullOrEmpty($value))
Write-Host "Signing key length:" $(if ($value) { $value.Length } else { 0 })

Remove-Variable value

Expected output:

Signing key configured: True
Signing key length: 64
Step 3 — Retry the backup

Once the key is saved:

Restart Apache through the XAMPP Control Panel.

Try creating a backup in Backup & Restore again.

Keep a secure copy of the signing key. If backups already exist, changing the signing key may prevent verification of older backups. The password and signing key serve different purposes and must remain distinct.

Send me the Step 2 output, and we'll continue with the next error if one appears.

make all steps to follow to setup this only thos work 

Here is the complete step-by-step setup for your PK Document Tracking System (PK-DTS) automatic daily backup on Windows/XAMPP. Follow the steps in order. You do not need to repeat the commands that have already succeeded.

PK-DTS backup installation guide

Backup folder created

C:\PK-DTS-Backups

Backup password

You attempted to configure this; verify it is saved.

Signing key

Still needs to be configured and verified.

Step 1 — Open PowerShell as Administrator

Press the Windows key.

Type PowerShell.

Right-click Windows PowerShell.

Select Run as administrator.

Approve the security prompt.

Verify administrator access:

net session

If you see Access is denied, stop and reopen PowerShell as Administrator.

Step 2 — Verify your project files

Run:

Set-Location "C:\xampp\htdocs\pk_document_tracking_system"

Test-Path ".\tools\backup.php"
Test-Path ".\tools\install_backup_schedule.ps1"
Test-Path "C:\xampp\php\php.exe"

All three results should be True.

Step 3 — Check PHP requirements

Run:

& "C:\xampp\php\php.exe" -v
& "C:\xampp\php\php.exe" -m

Confirm PHP 8.0 or later and these extensions:

zip

mysqli

fileinfo

Then check the required functions:

& "C:\xampp\php\php.exe" -r "var_dump(function_exists('proc_open')); var_dump(class_exists('ZipArchive'));"

Both should return bool(true).

The ZIP encryption API can be checked with:

& "C:\xampp\php\php.exe" -r "var_dump(method_exists('ZipArchive', 'setEncryptionName'));"

This API check alone does not verify successful AES-256 encryption; the completed backup must be tested.

Step 4 — Configure the backup directory

Your C: drive exists, so use the folder you already created.

New-Item -ItemType Directory -Path "C:\PK-DTS-Backups" -Force

Save the directory as a Machine environment variable:

[Environment]::SetEnvironmentVariable(
    "PK_BACKUP_DIR",
    "C:\PK-DTS-Backups",
    [EnvironmentVariableTarget]::Machine
)

Verify it:

[Environment]::GetEnvironmentVariable(
    "PK_BACKUP_DIR",
    [EnvironmentVariableTarget]::Machine
)

Expected value: C:\PK-DTS-Backups.

Step 5 — Configure all required environment variables

The system needs these settings:

Variable

	

Required value




DB_HOST

	

Your database host, usually 127.0.0.1




DB_PORT

	

Usually 3306




DB_DATABASE

	

Your actual database name




DB_USERNAME

	

Your MariaDB username




DB_PASSWORD

	

Your actual database password




PK_BACKUP_PASSWORD

	

Strong, unique password of at least 12 characters




PK_BACKUP_SIGNING_KEY

	

Separate random secret of at least 32 characters




PK_BACKUP_DIR

	

C:\PK-DTS-Backups

Use the following commands to enter the database configuration and securely prompt for its password. Run them in elevated PowerShell.

function Set-MachineVariable {
    param([string]$Name, [string]$Value)

    [Environment]::SetEnvironmentVariable(
        $Name, $Value, [EnvironmentVariableTarget]::Machine
    )
}

$dbHost = Read-Host "Database host"
$dbPort = Read-Host "Database port"
$dbName = Read-Host "Database name"
$dbUser = Read-Host "Database username"
$dbSecure = Read-Host "Database password" -AsSecureString

$ptr = [IntPtr]::Zero
try {
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($dbSecure)
    $dbPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)

    Set-MachineVariable "DB_HOST" $dbHost
    Set-MachineVariable "DB_PORT" $dbPort
    Set-MachineVariable "DB_DATABASE" $dbName
    Set-MachineVariable "DB_USERNAME" $dbUser
    Set-MachineVariable "DB_PASSWORD" $dbPassword
}
finally {
    if ($ptr -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    }
    $dbPassword = $null
    Remove-Variable dbSecure -ErrorAction SilentlyContinue
}

Set-MachineVariable "PK_BACKUP_DIR" "C:\PK-DTS-Backups"

Write-Host "Database configuration saved."

Do not rerun this step unnecessarily if your database settings are already correct.

Step 6 — Configure the backup password

If you have not successfully saved PK_BACKUP_PASSWORD, run:

$secure = Read-Host "Enter backup password (12+ characters)" -AsSecureString
$ptr = [IntPtr]::Zero

try {
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)

    if ($password.Length -lt 12) {
        throw "Password must contain at least 12 characters."
    }

    [Environment]::SetEnvironmentVariable(
        "PK_BACKUP_PASSWORD",
        $password,
        [EnvironmentVariableTarget]::Machine
    )
}
finally {
    if ($ptr -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    }
    $password = $null
    Remove-Variable secure -ErrorAction SilentlyContinue
}

Write-Host "Backup password setup completed."

Store the password securely. If you already have encrypted backups, preserve the password that was used to create them.

Step 7 — Configure the signing key

Run this only if the signing key is missing or you have not yet configured it.

$keyBytes = New-Object byte[] 48
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()

try {
    $rng.GetBytes($keyBytes)
}
finally {
    $rng.Dispose()
}

$signingKey = [Convert]::ToBase64String($keyBytes).
    TrimEnd('=').
    Replace('+', '-').
    Replace('/', '_')

[Environment]::SetEnvironmentVariable(
    "PK_BACKUP_SIGNING_KEY",
    $signingKey,
    [EnvironmentVariableTarget]::Machine
)

$signingKey = $null
$keyBytes = $null

Write-Host "Signing key setup completed."

This generates a separate random secret. Do not change it casually after creating signed backups; you may need the original key to verify them.

Step 8 — Verify all configuration

Run:

$names = @(
    "DB_HOST",
    "DB_PORT",
    "DB_DATABASE",
    "DB_USERNAME",
    "DB_PASSWORD",
    "PK_BACKUP_PASSWORD",
    "PK_BACKUP_SIGNING_KEY",
    "PK_BACKUP_DIR"
)

foreach ($name in $names) {
    $value = [Environment]::GetEnvironmentVariable(
        $name,
        [EnvironmentVariableTarget]::Machine
    )

    [PSCustomObject]@{
        Variable = $name
        Configured = -not [string]::IsNullOrEmpty($value)
    }
}

All variables should show Configured = True. This check does not reveal your passwords.

Step 9 — Set the Windows timezone

Run:

Get-TimeZone

To set UTC+08:00:

Set-TimeZone -Id "Singapore Standard Time"
Get-TimeZone

This timezone matches Philippine local time.

Step 10 — Check MySQL executable paths

Run:

Test-Path "C:\xampp\mysql\bin\mysqldump.exe"
Test-Path "C:\xampp\mysql\bin\mysql.exe"

If both return True, configure the optional executable paths:

Set-MachineVariable "PK_MYSQLDUMP" "C:\xampp\mysql\bin\mysqldump.exe"
Set-MachineVariable "PK_MYSQL" "C:\xampp\mysql\bin\mysql.exe"

If either returns False, locate the actual executable before setting that variable.

Step 11 — Configure folder permissions

The Apache process and the scheduled task account both need appropriate access to C:\PK-DTS-Backups.

Identify the actual Windows accounts used by Apache and Task Scheduler. Then grant Modify permission to those accounts, for example:

icacls "C:\PK-DTS-Backups" /grant "YOURPC\YourUser:(OI)(CI)M"

Replace the example account with the real account. Repeat for Apache or the scheduled task if they use different accounts. Avoid granting write permission to Everyone.

Step 12 — Install the scheduled task

Run in elevated PowerShell:

Set-Location "C:\xampp\htdocs\pk_document_tracking_system"

powershell -ExecutionPolicy Bypass -File .\tools\install_backup_schedule.ps1 `
  -ProjectRoot "C:\xampp\htdocs\pk_document_tracking_system" `
  -PhpExe "C:\xampp\php\php.exe"

If installation succeeds, inspect the task:

Get-ScheduledTask -TaskName "PKDTS-Daily-Backup-5PM"
Get-ScheduledTaskInfo -TaskName "PKDTS-Daily-Backup-5PM"

Verify the task's run-as account, daily trigger, and environment configuration.

Step 13 — Restart Apache and test the backup

Restart Apache from the XAMPP Control Panel.

Ensure MariaDB is running.

Start the task manually:

Start-ScheduledTask -TaskName "PKDTS-Daily-Backup-5PM"

Start-Sleep -Seconds 5

Get-ScheduledTaskInfo -TaskName "PKDTS-Daily-Backup-5PM"

Check for a newly created ZIP in C:\PK-DTS-Backups.

Open PK-DTS and go to Backup & Restore.

Confirm that an Automatic backup appears.

Test restoration using a separate application and database copy.

A task that starts successfully is not proof that the backup itself completed. Check the application logs and verify the archive and its encryption/signature.

Final checklist

Installation status

0/7 complete

PHP and required extensions verified
All eight Machine variables configured
Backup directory permissions set
UTC+08:00 timezone verified
Scheduled task installed
Manual backup creates a valid ZIP
Restore tested on a separate copy