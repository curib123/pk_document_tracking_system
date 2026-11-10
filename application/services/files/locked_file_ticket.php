<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Ten-minute, file-scoped preview capability for desktop apps without browser cookies. */
class Locked_file_ticket
{
    private function key(): string
    {
        $path = PK_ROOT . '/storage/.locked-review-key';
        if (is_link($path))
            throw new DomainException('File preview is temporarily unavailable.');
        $handle = @fopen($path, 'c+b');
        if (!$handle)
            throw new DomainException('File preview is temporarily unavailable.');
        try {
            if (!flock($handle, LOCK_EX))
                throw new DomainException('File preview is temporarily unavailable.');
            $key = stream_get_contents($handle);
            if ($key === '') {
                $key = random_bytes(32);
                if (fwrite($handle, $key) !== 32 || !fflush($handle))
                    throw new DomainException('File preview is temporarily unavailable.');
                chmod($path, 0600);
            }
            if (strlen($key) !== 32)
                throw new DomainException('File preview is temporarily unavailable.');
            return $key;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function issue(array $user, int $fileId, ?int $now = NULL): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode([
            'user_id' => (int) $user['id'],
            'session_version' => (int) $user['session_version'],
            'file_id' => $fileId,
            'expires' => ($now ?? time()) + 600,
            'nonce' => bin2hex(random_bytes(8))
        ])), '+/', '-_'), '=');

        $signature = hash_hmac('sha256', $payload, $this->key());

        return "{$payload}.{$signature}";
    }

    public function verify(string $ticket, int $fileId, ?int $now = NULL): array
    {
        if (
            strlen($ticket) > 1024 || !preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/D', $ticket, $match) ||
            !hash_equals(hash_hmac('sha256', $match[1], $this->key()), $match[2])
        )
            throw new DomainException('Preview link is invalid or expired. Reopen Preview.');
        $claims = json_decode((string) base64_decode(strtr($match[1], '-_', '+/'), TRUE), TRUE);
        $now = $now ?? time();
        if (
            !is_array($claims) || ($claims['file_id'] ?? 0) !== $fileId ||
            ($claims['expires'] ?? 0) <= $now || $claims['expires'] > $now + 600
        )
            throw new DomainException('Preview link is invalid or expired. Reopen Preview.');
        return $claims;
    }
}
