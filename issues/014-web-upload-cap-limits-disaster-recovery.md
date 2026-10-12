# Issue 014: Browser import cannot accept complete backups over 2 GiB

Severity: MEDIUM (deployment constraint) | Status: Confirmed hard cap | Implemented: NO

Affected: application/services/backup/backup_service.php::receiveUpload(); application/view/pages/administration/backups.php.

receiveUpload() rejects ZIPs larger than 2,147,483,648 bytes. Apache/PHP post_max_size, upload_max_filesize and request timeouts can be lower. Full source + documents + database can exceed this as archives grow.

## Copyable manual recovery path for large archives

Rather than increasing HTTP limits without a security review, copy the verified ZIP to the private backup directory via the server console. Use the CLI verification before restoring.

~~~powershell
# On the trusted server, adjust paths to the actual deployment.
$source = "D:/secure-backups/pk-dts-manual-20261013-170000-0123456789.zip"
$destination = "C:/xampp/htdocs/pk_document_tracking_system/storage/backups"
Copy-Item -LiteralPath $source -Destination $destination -ErrorAction Stop
Set-Location "C:/xampp/htdocs/pk_document_tracking_system"
& "C:/xampp/php/php.exe" tools/backup.php verify (Split-Path -Leaf $source)
~~~

Use the UI to select the verified imported archive if it appears in the private directory. For an offline restore when the website is not operational, use the documented CLI restore with --confirm only after preparing a maintenance backup and testing on a clone.

## Verify
Create a 2.1 GiB test ZIP in a non-production backup folder. Browser import must show a clear size-limit error; copying it to the private folder and CLI verification must remain possible. Do not disable signature or path checks to work around upload limits.
