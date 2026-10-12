# Issue 005: Failed rollback may leave decrypted staged database and private files

Severity: HIGH (privacy) | Status: Confirmed cleanup path | Implemented: NO

Affected: application/services/backup/backup_service.php::restore().

The catch branch creates $recoverStage, then removes it only after a successful recovery. If extraction or re-import throws, the decrypted temporary SQL and controlled files may remain under storage/backups/.stage-*.

## Copyable replacement for the inner recovery try/catch

Keep the outer restore lock and maintenance logic. Replace only the inner recovery attempt with:

~~~php
$recoverStage = NULL;
try {
    $recoverStage = $this->temporaryDirectory();
    $recoveryArchive = $this->resolve($recovery['name']);
    $recoveryManifest = $this->verify($recoveryArchive);
    $this->extractRestorable(
        $recoveryArchive, $recoveryManifest, $recoverStage
    );
    $this->importSql($recoverStage.'/database.sql');
    if (is_dir($recoverStage.'/storage')) {
        $this->copyDirectory(
            $recoverStage.'/storage',
            $this->root.'/storage'
        );
    }
} catch (Throwable $rollback) {
    $leaveMaintenance = TRUE;
    error_log(
        'PK DTS RESTORE rollback needs intervention: '.$rollback->getMessage()
    );
    throw new DomainException(
        'Restore failed and automatic recovery was incomplete. Use the recovery ZIP offline.'
    );
} finally {
    if ($recoverStage !== NULL) {
        $this->removeDirectory($recoverStage);
    }
}
throw new DomainException('Restore failed; the recovery backup was reapplied.');
~~~

## Verify
On a disposable clone, force both normal restoration and recovery restoration to fail. Confirm all .stage-* directories are removed, the encrypted recovery ZIP is retained, and storage/.restore-in-progress stays present when automatic recovery was incomplete. Never practice destructive restore-failure injection against production.
