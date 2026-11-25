<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Renders Word/Excel attachments to PDF with LibreOffice for read-only preview and printing.
 *
 * The uploaded original is never modified or replaced: it stays in storage/documents and is
 * what authorized roles download. The derived PDF is a disposable cache in storage/previews,
 * keyed by the (immutable, random) storage name of the original.
 */
class Pdf_preview_service
{
    private const CONVERT_TIMEOUT = 90;
    private const OFFICE_EXTENSIONS = ['docx', 'xlsx'];

    /** @return string 'docx'|'xlsx' when the file must be converted, otherwise ''. */
    public static function office_extension(array $file): string
    {
        $extension = strtolower(pathinfo((string) ($file['original_name'] ?? ''), PATHINFO_EXTENSION));
        return in_array($extension, self::OFFICE_EXTENSIONS, TRUE) ? $extension : '';
    }

    /** Absolute path of the cached PDF, converting on first use. Throws DomainException on failure. */
    public function pdf_path(array $file): string
    {
        $extension = self::office_extension($file);
        $storage = (string) ($file['storage_name'] ?? '');
        if ($extension === '' || !preg_match('/^[a-f0-9]{64}$/D', $storage)) {
            throw new DomainException('This file cannot be previewed as a PDF.');
        }
        $source = PK_ROOT . '/storage/documents/' . $storage;
        if (!is_file($source) || is_link($source)) {
            throw new DomainException('The document file is unavailable.');
        }
        $directory = $this->directory();
        $target = $directory . '/' . $storage . '.pdf';
        if ($this->usable($target)) {
            return $target;
        }

        // LibreOffice cannot share one profile between instances, so conversions run one at a time.
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::CONVERT_TIMEOUT * 2);
        }
        // Do not hold the session lock while converting; the modal requests several files at once.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $lock = @fopen($directory . '/.convert.lock', 'c');
        if (!$lock) {
            throw new DomainException('PDF preview is temporarily unavailable.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new DomainException('PDF preview is temporarily unavailable.');
            }
            if ($this->usable($target)) {
                return $target;
            }
            $this->convert($source, $extension, $directory, $target);
            return $target;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Remove the derived PDF when its original bytes are removed. */
    public static function forget(string $storage): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $storage)) {
            return;
        }
        $path = PK_ROOT . '/storage/previews/' . $storage . '.pdf';
        if (is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }

    private function directory(): string
    {
        $directory = PK_ROOT . '/storage/previews';
        if (!is_dir($directory) && !@mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
            throw new DomainException('PDF preview storage is unavailable.');
        }
        return $directory;
    }

    private function usable(string $path): bool
    {
        return is_file($path) && !is_link($path) && filesize($path) > 0;
    }

    private function convert(string $source, string $extension, string $directory, string $target): void
    {
        $binary = $this->binary();
        $work = $directory . '/.work-' . bin2hex(random_bytes(8));
        if (!@mkdir($work, 0700)) {
            throw new DomainException('PDF preview storage is unavailable.');
        }
        try {
            // LibreOffice picks the import filter from the extension, which stored files do not have.
            $input = $work . '/source.' . $extension;
            if (!@copy($source, $input)) {
                throw new DomainException('The document file is unavailable.');
            }
            $profile = str_replace('\\', '/', $directory . '/.lo-profile');
            $profileUrl = 'file://' . ($profile[0] === '/' ? '' : '/') . str_replace(' ', '%20', $profile);
            $this->run([
                $binary, '--headless', '--norestore', '--nologo', '--nolockcheck', '--nodefault',
                '-env:UserInstallation=' . $profileUrl,
                '--convert-to', 'pdf', '--outdir', $work, $input
            ]);
            $output = $work . '/source.pdf';
            if (!is_file($output) || filesize($output) < 8 || file_get_contents($output, FALSE, NULL, 0, 5) !== '%PDF-') {
                throw new RuntimeException('LibreOffice produced no PDF.');
            }
            // Atomic publish so a concurrent reader never sees a partial file.
            $staged = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (!@rename($output, $staged)) {
                throw new RuntimeException('Could not publish the converted PDF.');
            }
            @chmod($staged, 0600);
            if (!@rename($staged, $target)) {
                @unlink($staged);
                if (!$this->usable($target)) {
                    throw new RuntimeException('Could not publish the converted PDF.');
                }
            }
        } catch (DomainException $error) {
            throw $error;
        } catch (Throwable $error) {
            log_message('error', 'PDF preview conversion failed: ' . $error->getMessage());
            throw new DomainException('This file could not be converted to PDF for preview. Please try again or contact your administrator.');
        } finally {
            $this->remove_tree($work);
        }
    }

    private function binary(): string
    {
        $configured = trim((string) (getenv('PK_LIBREOFFICE_PATH') ?: ''));
        $candidates = array_filter([
            $configured,
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/usr/local/bin/soffice',
            '/opt/libreoffice/program/soffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice'
        ]);
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        log_message('error', 'PDF preview unavailable: LibreOffice was not found. Set PK_LIBREOFFICE_PATH.');
        throw new DomainException('PDF preview is not available because LibreOffice is not installed on the server.');
    }

    private function run(array $command): void
    {
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $process = @proc_open($command, [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start LibreOffice.');
        }
        $deadline = microtime(TRUE) + self::CONVERT_TIMEOUT;
        while (TRUE) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                proc_close($process);
                return;
            }
            if (microtime(TRUE) > $deadline) {
                $this->kill((int) $status['pid']);
                proc_close($process);
                throw new RuntimeException('LibreOffice timed out.');
            }
            usleep(100000);
        }
    }

    /** soffice.exe hands off to soffice.bin on Windows, so end the whole tree. */
    private function kill(int $pid): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            @exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
        } elseif (function_exists('posix_kill')) {
            @posix_kill($pid, 9);
        }
    }

    private function remove_tree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            is_dir($child) && !is_link($child) ? $this->remove_tree($child) : @unlink($child);
        }
        @rmdir($path);
    }
}
