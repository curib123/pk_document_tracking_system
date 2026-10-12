# Issue 015: Backup screen does not warn when the scheduled 5 PM backup was missed

Severity: MEDIUM-HIGH (recoverability) | Status: Confirmed missing health-check feature | Implemented: NO

Affected: application/controllers/Backups.php::index(); application/view/pages/administration/backups.php.

The Backup & Restore page displays the latest Automatic archive's modification time, but it does not compare that time with the most recent scheduled 5 PM slot. A broken or disabled Windows task can remain unnoticed for days.

## Copyable diagnostic (in Backups::index())

Immediately after obtaining $service, calculate the last expected local run:

~~~php
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$expected = $now->setTime(17,0);
if ($now < $expected) $expected = $expected->modify('-1 day');
$last = $this->lastAuto($service->list_backups());
$lastTime = $last ? (int)$last['created_at'] : 0;
$backupMissed = $now > $expected->modify('+30 minutes') &&
    $lastTime < $expected->getTimestamp();
~~~

Pass backupMissed to the existing render() data array. In the backup view, add an existing-style warning only when true:

~~~php
<?php if (!empty($backupMissed)): ?>
  <div class="backup-setup-note" role="status">
    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
    The scheduled 5:00 PM backup has not been recorded. Check Task Scheduler.
  </div>
<?php endif; ?>
~~~

This checks backups on page view; for actual unattended alerts a server monitoring system must send email/SMS or create a separate scheduled condition checker.

## Verify
Use an empty backup directory and visit after 5:30 PM Philippines time: show warning. Add a valid current Automatic ZIP: hide warning. Visit before 5 PM and after midnight: make sure the expected-day logic works. Test server uptime and scheduler errors independently.
