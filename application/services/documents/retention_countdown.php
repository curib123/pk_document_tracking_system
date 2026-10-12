<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Human-readable time remaining until the hardcopy's retention end date.
 * Presentation only: never schedules or executes disposal.
 * Uses calendar days in the office time zone, not 24-hour intervals.
 * Compatible with PHP 8.0 and independent of database state.
 */
final class Retention_countdown
{
    public static function label($enabled, $endDate, ?DateTimeImmutable $today = NULL): string
    {
        if ((int)$enabled !== 1) return 'No retention';

        $endText=trim((string)$endDate);
        if ($endText==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endText))
            return 'No retention';

        $timezone=new DateTimeZone('Asia/Manila');
        $end=DateTimeImmutable::createFromFormat('!Y-m-d', $endText, $timezone);
        if (!$end || $end->format('Y-m-d')!==$endText) return 'No retention';

        $now=$today ?? new DateTimeImmutable('now', $timezone);
        $current=$now->setTimezone($timezone)->setTime(0,0,0);
        $days=(int)$current->diff($end)->format('%r%a');

        if ($days===0) return 'Due today';
        if ($days<0) {
            $days=abs($days);
            return 'Overdue by '.$days.' '.($days===1?'day':'days');
        }

        return $days.' '.($days===1?'day':'days').' remaining';
    }
}
