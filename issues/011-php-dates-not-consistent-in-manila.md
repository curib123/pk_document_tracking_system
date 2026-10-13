# Issue 011: Different modules use different server date/time zones

Severity: MEDIUM (data consistency) | Status: Confirmed source inconsistency | Implemented: NO

Affected: application/bootstrap.php; application/controllers/Auth.php; application/services/transfer/transfer_service.php; application/services/softcopy/softcopy_operation_service.php.

Some modules explicitly create Asia/Manila DateTimeImmutable values; others use PHP date() and strtotime() with the machine's current default timezone. A Windows server or Linux container set to UTC will produce mixed transaction dates, approval dates and UI dates.

## Copyable fix

In application/bootstrap.php, immediately after defining PK_ROOT and before any service bootstrap:

~~~php
date_default_timezone_set('Asia/Manila');
~~~

In the cron/Task Scheduler process keep Asia/Manila configured separately (the backup CLI already sets it). Do not alter existing database timestamps without a controlled migration; MariaDB TIMESTAMP values may be stored in UTC, so document expected conversions separately.

## Verify
On a disposable installation, test with PHP's operating-system zone set to UTC, then Asia/Manila. Insert a login attempt, direct Softcopy revision and Hardcopy transfer. Ensure local-time approval dates and daily JSON audit filenames match the same Philippine calendar day. Test around 00:15 and 23:45 local time.
