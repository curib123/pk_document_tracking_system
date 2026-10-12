<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require_once __DIR__.'/../application/services/documents/retention_countdown.php';

function expect_label(string $expected, $enabled, $endDate, DateTimeImmutable $today): void
{
    $actual=Retention_countdown::label($enabled,$endDate,$today);
    if ($actual!==$expected)
        throw new RuntimeException('Expected "'.$expected.'", got "'.$actual.'".');
}

$tz=new DateTimeZone('Asia/Manila');
$today=new DateTimeImmutable('2026-10-12 14:00:00',$tz);

// Example: Oct 12 to Oct 31 is 19 calendar days, not 18 or 20.
expect_label('19 days remaining',1,'2026-10-31',$today);
expect_label('1 day remaining',1,'2026-10-13',$today);
expect_label('Due today',1,'2026-10-12',$today);
expect_label('Overdue by 1 day',1,'2026-10-11',$today);
expect_label('Overdue by 3 days',1,'2026-10-09',$today);
expect_label('No retention',0,'2026-10-31',$today);
expect_label('No retention',FALSE,'2026-10-31',$today);
expect_label('No retention',1,NULL,$today);
expect_label('No retention',1,'',$today);
expect_label('No retention',1,'0000-00-00',$today);
expect_label('No retention',1,'2026-02-30',$today);
expect_label('No retention',1,'2026/10/31',$today);
expect_label('1 day remaining',1,'2028-02-29',new DateTimeImmutable('2028-02-28',$tz));
expect_label('1 day remaining',1,'2027-01-01',new DateTimeImmutable('2026-12-31',$tz));

// The user's office day, not the server UTC day, determines the countdown.
$utc=new DateTimeImmutable('2026-10-11 16:30:00',new DateTimeZone('UTC'));
expect_label('19 days remaining',1,'2026-10-31',$utc);

echo "Retention countdown date, overdue, timezone, and no-retention tests passed.\n";
