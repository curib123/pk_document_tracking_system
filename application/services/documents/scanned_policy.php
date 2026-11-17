<?php
defined('BASEPATH') OR exit('No direct script access allowed');

final class Scanned_policy
{
    public const TYPES = ['scanned_create', 'scanned_update', 'scanned_delete', 'scanned_category_create'];

    public static function action(string $type): string
    {
        if (!in_array($type, self::TYPES, TRUE))
            throw new DomainException('Unsupported scanned document action.');
        return ['scanned_update' => 'edit', 'scanned_delete' => 'delete'][$type] ?? 'request';
    }

    public static function date(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
        if (!$date || $date->format('Y-m-d') !== $value)
            throw new DomainException('Enter a valid date in YYYY-MM-DD format.');
        return $value;
    }

    public static function metadata(string $type, array $payload): void
    {
        self::action($type);
        $reason = trim((string) ($payload['remarks'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 2000)
            throw new DomainException('A remark / reason is required (maximum 2000 characters).');
        if ($type === 'scanned_delete') return;
        $title = trim((string) ($payload['title'] ?? ''));
        $max = $type === 'scanned_category_create' ? 150 : 255;
        if ($title === '' || mb_strlen($title) > $max)
            throw new DomainException('A title is required (maximum ' . $max . ' characters).');
        if ($type === 'scanned_category_create') return;
        $start = self::date((string) ($payload['retention_start_date'] ?? ''));
        $end = self::date((string) ($payload['retention_end_date'] ?? ''));
        if ($end < $start)
            throw new DomainException('Retention end date must be on or after its start date.');
    }

    public static function countdown(string $end, ?DateTimeImmutable $now = NULL): string
    {
        self::date($end);
        $zone = new DateTimeZone('Asia/Manila');
        // A date-only retention period includes the entire end date.
        $expiry = (new DateTimeImmutable($end, $zone))->modify('+1 day')->setTime(0, 0);
        $seconds = $expiry->getTimestamp() - ($now ?? new DateTimeImmutable('now', $zone))->getTimestamp();
        $hours = (int) ceil(abs($seconds) / 3600);
        return ($seconds <= 0 ? 'Expired: ' : '') . intdiv($hours, 24) . ' days ' . ($hours % 24) .
            ($seconds <= 0 ? ' hours overdue' : ' hours remaining');
    }
}
