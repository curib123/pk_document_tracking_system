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
