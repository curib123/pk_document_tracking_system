# ISSUE 015 — Ordinary web requests trigger destructive audit/ZIP cleanup

**Severity:** High (governance, recovery policy)  
**Type:** Confirmed bootstrap side effect  
**Status:** Proposed fix only; NOT applied

## Evidence
- application/bootstrap.php constructs Backup_service and calls apply_retention_daily() during bootstrap.
- It catches failures, but a request to the login page is sufficient to trigger filesystem deletion of backups/audit/log files older than three months.
- The user may believe retention occurs only when the automatic schedule runs or an administrator presses Clean up now. This can make old evidence disappear upon the first request after a long outage.

## Copyable fix — move maintenance to explicit tasks
In application/bootstrap.php remove the try/catch that calls apply_retention_daily() at HTTP bootstrap time. Leave the directory initialization untouched:

~~~php
foreach (['storage','storage/logs','storage/sessions','storage/documents'] as $part) {
    $directory = PK_ROOT . '/' . $part;
    if (!is_dir($directory)) mkdir($directory, 0700, TRUE);
}
// Do not run retention cleanup as a side effect of ordinary HTTP requests.
~~~

Existing Backup_service::backup() already calls the retention service AFTER a successful scheduled/manual backup, and Backups::cleanup() offers explicit manual cleanup. Install/verify the Windows 5 PM task if you want maintenance to run without a browser visit.

Before changing retention behavior, confirm the organization's legally required records-retention term; three months of audit history may be unacceptable for compliance or dispute resolution.

## Verify
On a disposable XAMPP copy, create expired sample audit and backup archives. Open login/dashboard: no files should disappear. Trigger the scheduled backup or authorized Clean up now: eligible files should be processed according to approved policy, while at least one independently verified recovery archive is preserved.
