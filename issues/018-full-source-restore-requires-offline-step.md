# Issue 018: Browser Restore cannot recover the executable application code

Severity: MEDIUM (disaster recovery completeness) | Status: Confirmed intentional safety boundary | Implemented: NO

Affected: application/services/backup/backup_service.php::restore(); docs/backup-restore.md.

The ZIP contains application code, public assets and dependencies, but browser Restore intentionally imports ONLY the database and private storage. If the PHP application itself is damaged or deleted, clicking Restore in the web interface cannot reconstruct it. This was a deliberate protection against replacing running executable code through an upload.

## Copyable OFFLINE recovery procedure — NOT for an active production server

1. Stop Apache on the affected server and preserve the current folder as an emergency copy.
2. Use a trusted archive that has passed verification with the matching signing key:

~~~powershell
Set-Location "C:/xampp/htdocs/pk_document_tracking_system"
& "C:/xampp/php/php.exe" tools/backup.php verify "pk-dts-auto-YYYYMMDD-HHMMSS-0123456789.zip"
~~~

3. From a trusted archive tool such as 7-Zip, extract the encrypted archive into a NEW private folder, entering the password interactively. Review the signed manifest; copy the files/application, files/public, files/vendor, and other code/config trees back to their corresponding locations while Apache is stopped.
4. Keep the current DB connection variables and encryption key in your secure external configuration. The .env environment is intentionally NOT included.
5. Restore the database and private files using the documented CLI tool, then run PHP lint, database migrations/contract tests and smoke checks before restarting Apache.

## Verify
In a spare XAMPP installation, delete a disposable copy of a controller or static asset, restore it from a verified ZIP, start the cloned site and verify matching version, files, approved attachments, user sessions and workflows. Do not permit web uploads to overwrite PHP code directly.
