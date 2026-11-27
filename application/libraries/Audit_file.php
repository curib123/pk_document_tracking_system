<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Per-day valid JSON-array audit files, outside public/, never database rows.
 * Records only action metadata, never passwords, request bodies or attachments.
 */
class Audit_file
{
    public function append($actorId, $action, $subject = NULL, $meta = [], $status = 'success')
    {
        $dir = PK_ROOT . '/storage/audit';
        if (!is_dir($dir) && !mkdir($dir, 0700, TRUE))
            return FALSE;
        $day = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))
            ->format('Y-m-d');
        $path = $dir . '/' . $day . '.json';
        $lock = fopen($dir . '/.audit.lock', 'c');
        if (!$lock)
            return FALSE;
        try {
            if (!flock($lock, LOCK_EX))
                return FALSE;
            $entries = is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : [];
            if (!is_array($entries))
                return FALSE;
            $entries[] = [
                'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))
                    ->format(DATE_ATOM),
                'actor_id' => (int) $actorId,
                'action' => substr((string) $action, 0, 120),
                'subject' => $subject === NULL ? NULL : (string) $subject,
                'status' => in_array($status, ['success', 'failed'], TRUE) ? $status : 'failed',
                'metadata' => is_array($meta) ? $meta : []
            ];
            $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === FALSE)
                return FALSE;
            $temp = tempnam($dir, '.audit-');
            if (!$temp)
                return FALSE;
            try {
                $contents = $json . "\n";
                if (file_put_contents($temp, $contents) !== strlen($contents))
                    return FALSE;
                chmod($temp, 0600);
                // Windows readers can temporarily prevent replacement of the daily file.
                $attempts = DIRECTORY_SEPARATOR === '\\' ? 11 : 1;
                for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                    if (@rename($temp, $path))
                        return TRUE;
                    if ($attempt < $attempts)
                        usleep(20000);
                }
                return FALSE;
            } finally {
                if (is_file($temp))
                    @unlink($temp);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
