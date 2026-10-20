<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Resolve document titles consistently in My Requests and My Tasks. */
final class Request_title
{
    public static function resolve(string $type, array $payload, string $sourceTitle, int $requestId): string
    {
        $proposed = trim((string) ($payload['title'] ?? ''));
        $existing = trim($sourceTitle);
        $proposesTitle = in_array(
            $type, ['softcopy_create', 'softcopy_revise', 'hardcopy_create', 'hardcopy_update'], TRUE
        );
        $primary = $proposesTitle ? $proposed : $existing;
        $secondary = $proposesTitle ? $existing : $proposed;
        if ($primary !== '')
            return $primary;
        if ($secondary !== '')
            return $secondary;
        $subject = trim((string) ($payload['subject'] ?? ''));
        return $subject !== '' ? $subject : 'Request #' . $requestId;
    }
}
