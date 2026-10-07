<?php
declare(strict_types=1);

use Pk\Core\{Context, Problem, Rules};

class Auth_service
{
    // Auth flow simple ra: validate -> rate-limit -> verify -> refresh session.
    private Context $ctx;

    public function __construct(Context|array|null $options = null)
    {
        $this->ctx = Context::fromOptions($options);
    }

    private function model(): Auth_model
    {
        return $this->ctx->model(\Auth_model::class);
    }

    public function session(): array
    {
        return [
            'user' => $this->ctx->safeUser(),
            'permissions' => $this->ctx->id()
                ? $this->ctx->permissions()
                : [],
        ];
    }

    public function login(array $input): array
    {
        $username = Rules::text(
            $input,
            'username',
            80
        );

        $password = (string) (
            $input['password'] ?? ''
        );

        $attemptKey = hash(
            'sha256',
            strtolower($username)
            . '|'
            . ($_SERVER['REMOTE_ADDR'] ?? 'local')
        );

        // Failed-login counters must commit even if credentials are wrong.
        $result = $this->model()->transaction(
            fn() => $this->authenticate(
                $attemptKey,
                $username,
                $password
            )
        );

        if (isset($result['error'])) {
            throw new Problem(
                $result['error'],
                $result['status']
            );
        }

        session_regenerate_id(true);

        $_SESSION = [
            'user_id' => (int) $result['user']['id'],
            'session_version' =>
                (int) $result['user']['session_version'],
            'csrf' => bin2hex(random_bytes(32)),
            'last_seen' => time(),
        ];

        return [
            'user' => $this->ctx->safeUser(),
            'permissions' => $this->ctx->permissions(),
        ];
    }

    private function authenticate(
        string $attemptKey,
        string $username,
        string $password
    ): array {
        $db = $this->model();

        $db->ensure_attempt([$attemptKey]);

        $attempt = $db->lock_attempt(
            [$attemptKey]
        );

        $expiredWindow =
            strtotime($attempt['window_started'])
            < time() - 900;

        if ($expiredWindow) {
            $db->reset_attempt([$attemptKey]);
            $attempt['failures'] = 0;
        }

        if ((int) $attempt['failures'] >= 8) {
            return [
                'error' => 'Too many attempts. Try again after 15 minutes.',
                'status' => 429,
            ];
        }

        $user = $db->user_by_username(
            [$username]
        );

        // Dummy hash keeps timing similar even when the username does not exist.
        $dummyHash =
            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

        $validPassword = password_verify(
            $password,
            $user['password_hash'] ?? $dummyHash
        );

        $validAccount =
            $validPassword
            && $user
            && (int) $user['active'] === 1
            && (int) $user['role_active'] === 1;

        if (!$validAccount) {
            $db->increment_attempt([$attemptKey]);

            $this->ctx->audit(
                'auth',
                'login_failed',
                null,
                null,
                [
                    'attempted_username' => $username,
                ]
            );

            return [
                'error' => 'Invalid username or password.',
                'status' => 401,
            ];
        }

        $db->clear_attempt([$attemptKey]);

        $this->ctx->identify(
            (int) $user['id']
        );

        $this->ctx->audit(
            'auth',
            'login',
            (int) $user['id']
        );

        return [
            'user' => $user,
        ];
    }

    public function changePassword(array $input): array
    {
        if (!$this->ctx->id()) {
            throw new Problem(
                'Sign in first.',
                401
            );
        }

        $user = $this->model()->lock(
            'users',
            $this->ctx->id()
        );

        $currentPassword = (string) (
            $input['current_password'] ?? ''
        );

        if (
            !password_verify(
                $currentPassword,
                $user['password_hash']
            )
        ) {
            throw new Problem(
                'Current password is incorrect.'
            );
        }

        $newPassword = (string) (
            $input['new_password'] ?? ''
        );

        if (
            password_verify(
                $newPassword,
                $user['password_hash']
            )
        ) {
            throw new Problem(
                'Choose a different password.'
            );
        }

        $hash = Rules::password(
            $newPassword,
            (string) (
                $input['confirm_password'] ?? ''
            )
        );

        $nextSessionVersion =
            (int) $user['session_version'] + 1;

        $this->model()->update(
            'users',
            $this->ctx->id(),
            [
                'password_hash' => $hash,
                'require_password_change' => 0,
                'session_version' => $nextSessionVersion,
            ]
        );

        $_SESSION['session_version'] =
            $nextSessionVersion;

        $_SESSION['csrf'] =
            bin2hex(random_bytes(32));

        session_regenerate_id(true);

        $this->ctx->audit(
            'auth',
            'password_changed',
            $this->ctx->id()
        );

        return [
            'message' => 'Password changed. Other sessions have been invalidated.',
        ];
    }

    public function logout(): array
    {
        $this->ctx->audit(
            'auth',
            'logout',
            $this->ctx->id()
        );

        $_SESSION = [];
        session_regenerate_id(true);

        $_SESSION['csrf'] =
            bin2hex(random_bytes(32));

        return [
            'message' => 'Signed out.',
        ];
    }
}
