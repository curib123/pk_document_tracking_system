<?php
declare(strict_types=1);

namespace Pk\Core;

final class Security
{
    // Security basics diri centralized para same behavior sa session, CSRF, ug HTTP methods.

    public static function method(
        string $method,
        bool $mutation
    ): void {
        $expected = $mutation
            ? 'POST'
            : 'GET';

        if ($method !== $expected) {
            throw new Problem(
                'HTTP method not allowed.',
                405
            );
        }
    }

    public static function csrf(
        string $expected,
        string $supplied
    ): bool {
        $valid =
            strlen($expected) === 64
            && hash_equals(
                $expected,
                $supplied
            );

        if (!$valid) {
            throw new Problem(
                'This form expired. Refresh the page and try again.',
                419
            );
        }

        return true;
    }

    public static function expired(
        array $session,
        int $now
    ): bool {
        if (!isset($session['last_seen'])) {
            return true;
        }

        return
            $now - (int) $session['last_seen']
            > 1800;
    }

    public static function startSession(): void
    {
        if (
            session_status()
            === PHP_SESSION_ACTIVE
        ) {
            return;
        }

        ini_set(
            'session.use_strict_mode',
            '1'
        );

        ini_set(
            'session.use_only_cookies',
            '1'
        );

        ini_set(
            'session.gc_maxlifetime',
            '1800'
        );

        session_name('pk_dts_session');

        session_set_cookie_params(
            [
                'lifetime' => 0,
                'path' => '/',
                'secure' =>
                    getenv('SESSION_SECURE') === '1',
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );

        session_start();

        if (!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] =
                bin2hex(random_bytes(32));
        }
    }

    public static function headers(): void
    {
        header(
            'X-Content-Type-Options: nosniff'
        );

        header(
            'X-Frame-Options: DENY'
        );

        header(
            'Referrer-Policy: same-origin'
        );

        // Tailwind stays local. Only the requested font/icon CSS origins are added.
        // No remote scripts, inline CSS/JS or wildcard origins are permitted.
        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self'; " .
            "style-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
            "img-src 'self'; " .
            "object-src 'none'; " .
            "base-uri 'self'; " .
            "frame-ancestors 'none'; " .
            "form-action 'self'; " .
            "connect-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com"
        );

        header(
            'Cache-Control: no-store, private'
        );
    }
}
