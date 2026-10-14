# ISSUE 006 — Completed ZIP is published before verifying its own signed content

**Severity:** High (restore integrity, conditional on concurrent file writes)  
**Type:** Confirmed ordering / reliability gap  
**Status:** Proposed fix only; NOT applied

## Evidence
- Backup_service::createLocked() enqueues source files using ZipArchive::addFile() and calculates source SHA-256 hashes while iterating files.
- ZipArchive may read source file bytes during close(), after the earlier hash calculation. A concurrent document update can cause ZIP bytes to differ from the signed hashes.
- The archive is renamed into the visible backup list immediately after close(), without a verify() pass.

## Copyable fix
In application/services/backup/backup_service.php::createLocked(), after a successful close() and chmod() and immediately BEFORE rename() of $tempArchive, insert:

~~~php
// Verify what the ZIP actually contains, not only the bytes observed
// when the manifest hashes were created.
$this->verify($tempArchive);
~~~

The existing finally block deletes the temp ZIP on failure, so corrupt archives are not published. Retain the existing filename/format, signing key, password and lock; do not skip verify() to speed up large backups.

## Verify
Create a normal encrypted ZIP; verify() must pass. On a test server, mutate a source document during a deliberately slow backup (e.g. a large controlled file) and check that an inconsistent archive fails and is NOT listed. Also test full recovery of a valid archive. Note that content integrity still does not provide a single atomic DB/file point-in-time snapshot.
