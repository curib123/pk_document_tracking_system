<?php
defined('BASEPATH') OR defined('PK_ROOT') OR exit('No direct script access allowed');

/** Three calendar months of managed archives and daily logs; never documents. */
final class Retention_service
{
    private $root;
    private $backups;
    private $timezone;

    public function __construct(string $root, string $backups)
    {
        $real = realpath($root);
        if (!$real)
            throw new DomainException('Retention application directory is unavailable.');
        $this->root = $this->normalized($real);
        $this->backups = $this->normalized($backups);
        $this->timezone = new DateTimeZone('Asia/Manila');
    }

    /** Caller holds Backup_service's archive/restore lock. */
    public function cleanup(?DateTimeImmutable $now = NULL): array
    {
        if (file_exists($this->root . '/storage/.restore-in-progress'))
            throw new DomainException('Retention is paused during restore maintenance.');
        $now = ($now ?? new DateTimeImmutable('now', $this->timezone))->setTimezone($this->timezone);
        // Start on day one before subtracting months, then clamp month ends.
        $month = $now->setDate((int) $now->format('Y'), (int) $now->format('m'), 1)->modify('-3 months');
        $cutoff = $month->setDate(
            (int) $month->format('Y'),
            (int) $month->format('m'),
            min((int) $now->format('d'), (int) $month->format('t'))
        );
        $directories = $this->directories();

        $protectedArchives = [];

        $names = glob($this->backups . '/pk-dts-*.zip') ?: [];
        $latestByClass = [];
        foreach ($names as $path) {
            if (!is_file($path) || is_link($path))
                continue;
            $name = basename($path);
            if (!$this->managedDate('backups', $name))
                continue;
            $kind = strpos($name, 'pk-dts-recovery-') === 0
                ? 'recovery' : 'regular';
            $stamp = (int) filemtime($path);
            if (
                !isset($latestByClass[$kind]) ||
                $stamp > $latestByClass[$kind]['stamp']
            ) {
                $latestByClass[$kind] = ['name' => $name, 'stamp' => $stamp];
            }
        }
        foreach ($latestByClass as $latest) {
            $protectedArchives[$latest['name']] = TRUE;
        }

        $result = [
            'cutoff' => $cutoff->format(DATE_ATOM),
            'deleted' => ['backups' => 0, 'audit' => 0, 'logs' => 0],
            'failed' => []
        ];
        foreach ($directories as $kind => $directory) {
            if ($directory === NULL)
                continue;
            $lock = NULL;
            try {
                if ($kind === 'audit') {
                    $path = $directory . '/.audit.lock';
                    if (is_link($path) || (file_exists($path) && !is_file($path))) {
                        $result['failed'][] = 'audit/.audit.lock';
                        continue;
                    }
                    $lock = @fopen($path, 'c');
                    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
                        $result['failed'][] = 'audit/.audit.lock';
                        continue;
                    }
                }
                $names = @scandir($directory);
                if ($names === FALSE) {
                    $result['failed'][] = $kind;
                    continue;
                }
                foreach ($names as $name) {
                    $created = $this->managedDate($kind, $name);
                    
                    if ($kind === 'backups' && isset($protectedArchives[$name])) {
                        continue;
                    }
                    if ($created === NULL)
                        continue;
                    // Daily logs contain a whole day's events: keep the cutoff day.
                    $expired = $kind === 'backups' ? $created < $cutoff :
                        $created->format('Y-m-d') < $cutoff->format('Y-m-d');
                    if (!$expired)
                        continue;
                    $path = $directory . '/' . $name;
                    clearstatcache(TRUE, $path);
                    if (is_link($path) || !is_file($path))
                        continue;
                    $real = realpath($path);
                    if (!$real || !$this->samePath($real, $path))
                        continue;
                    if (@unlink($path))
                        $result['deleted'][$kind]++;
                    else
                        $result['failed'][] = $kind . '/' . $name;
                }
            } finally {
                if (is_resource($lock)) {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            }
        }
        return $result;
    }

    /** A successful daily pass is checkpointed; incomplete passes retry later. */
    public function cleanup_daily(?DateTimeImmutable $now = NULL): ?array
    {
        $now = ($now ?? new DateTimeImmutable('now', $this->timezone))->setTimezone($this->timezone);
        $this->directories();
        $marker = $this->root . '/storage/.retention-day';
        if (is_link($marker) || (file_exists($marker) && !is_file($marker)))
            throw new DomainException('Retention checkpoint path is unsafe.');
        if (is_file($marker) && trim((string) @file_get_contents($marker)) === $now->format('Y-m-d'))
            return NULL;
        $result = $this->cleanup($now);
        if (!$result['failed'] && @file_put_contents($marker, $now->format('Y-m-d') . "\n", LOCK_EX) === FALSE)
            $result['failed'][] = 'storage/.retention-day';
        return $result;
    }

    private function directories(): array
    {
        $storage = $this->directory($this->root . '/storage', FALSE);
        return [
            'backups' => $this->directory($this->backups, FALSE),
            'audit' => $this->directory($storage . '/audit', TRUE),
            'logs' => $this->directory($storage . '/logs', TRUE)
        ];
    }
    private function directory(string $path, bool $optional): ?string
    {
        if ($optional && !file_exists($path) && !is_link($path))
            return NULL;
        $real = realpath($path);
        // Reject symlinks and Windows junctions redirecting storage/log folders.
        if (is_link($path) || !$real || !is_dir($path) || !$this->samePath($real, $path))
            throw new DomainException('Retention directory is unsafe or unavailable.');
        return $this->normalized($real);
    }
    private function managedDate(string $kind, string $name): ?DateTimeImmutable
    {
        if ($kind === 'backups') {
            if (!preg_match('/^pk-dts-(?:auto|manual|manual-import|recovery)-([0-9]{8}-[0-9]{6})-[a-f0-9]{10}\.zip$/D', $name, $match))
                return NULL;
            $format = 'Ymd-His';
        } elseif ($kind === 'audit') {
            if (!preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2})\.json$/D', $name, $match))
                return NULL;
            $format = 'Y-m-d';
        } else {
            if (!preg_match('/^log-([0-9]{4}-[0-9]{2}-[0-9]{2})\.log$/D', $name, $match))
                return NULL;
            $format = 'Y-m-d';
        }
        $date = DateTimeImmutable::createFromFormat('!' . $format, $match[1], $this->timezone);
        return $date && $date->format($format) === $match[1] ? $date : NULL;
    }
    private function normalized(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
    private function samePath(string $left, string $right): bool
    {
        $left = $this->normalized($left);
        $right = $this->normalized($right);
        return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($left, $right) === 0 : $left === $right;
    }
}
