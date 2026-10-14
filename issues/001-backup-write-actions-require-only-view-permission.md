# ISSUE 001 — Backup write/restore/cleanup actions are guarded only by View permission

**Severity:** High (conditional authorization/design flaw)  
**Type:** Confirmed source-level permission mismatch; exploitation depends on the permissions granted to actual roles  
**Status:** Proposed fix only; NOT applied

## Evidence and impact
- application/controllers/Backups.php::administrator() calls require_permission('backups','view').
- The shared execute() method calls administrator() for create, ZIP import, retention cleanup and restore. The same View permission also covers downloading complete sensitive archives.
- Permission_model supports explicit action keys. At present Administrator may be the only role eligible to view backups, but if an organization adds or delegates backups:view, it would also grant destructive restore and cleanup operations.

## Copyable fix
In application/controllers/Backups.php keep administrator() unchanged for index/download, but add a write guard:

~~~php
private function authorizeWrite(): void
{
    $this->require_permission('backups','edit');
}
~~~

In execute(), change the beginning to:

~~~php
private function execute($task): void
{
    $this->authorizeWrite();
    $this->confirmed();
    // Keep the existing set_time_limit, try/catch and redirect body.
~~~

If the organization wants only Administrators to restore regardless of backups:edit, add a dedicated restore authorization policy instead of reusing View. Do not weaken the current password and RESTORE phrase checks. Ensure the permissions seed / roles UI knows the intended backups:edit action before granting it to non-administrators.

## Verification
Create a test role with backups:view but not backups:edit (using a disposable DB). The user may open the list only if the organization's policy permits it, but POST create/import/cleanup/restore must respond 403. An authorized role with edit permission should still be able to use all intended actions. Confirm Administrator privileges still work. Inspect audit logs and CSRF enforcement. Test from a signed-in browser; do not run a real destructive restore just to prove permission checks.
