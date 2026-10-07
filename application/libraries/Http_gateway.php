<?php
declare(strict_types=1);

use Pk\Core\{
    Context,
    Database,
    Database_error,
    Problem,
    Rules,
    Security
};

class Http_gateway
{
    // HTTP plumbing ra ni: parse -> authorize -> service -> response.
    private Context $ctx;

    public function __construct()
    {
        Security::startSession();
        Security::headers();

        $this->ctx = new Context(
            Database::connect()
        );

        $this->restoreSession();
    }

    private function restoreSession(): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        try {
            if (Security::expired($_SESSION, time())) {
                throw new Problem(
                    'Your session expired.',
                    401
                );
            }

            $this->ctx->identify(
                (int) $_SESSION['user_id']
            );

            $currentVersion =
                (int) $this->ctx->user['session_version'];

            $sessionVersion =
                (int) ($_SESSION['session_version'] ?? 0);

            if ($currentVersion !== $sessionVersion) {
                throw new Problem(
                    'Sign in again.',
                    401
                );
            }

            $_SESSION['last_seen'] = time();
        } catch (Problem $e) {
            if ($e->status !== 401) {
                throw $e;
            }

            // Expired/invalid session? Clean slate lang, then require login again.
            $this->ctx->user = null;
            $_SESSION = [
                'csrf' => bin2hex(random_bytes(32)),
            ];

            session_regenerate_id(true);
        }
    }

    public function execute(
        array $definition,
        string $method,
        array $input,
        array $uploads = []
    ): array {
        $mutation =
            $definition['method'] === 'POST';

        $operation = $definition['op'];

        Security::method(
            $method,
            $mutation
        );

        if ($mutation) {
            Security::csrf(
                $_SESSION['csrf'],
                (string) (
                    $_SERVER['HTTP_X_CSRF_TOKEN']
                    ?? ''
                )
            );
        }

        $input = array_replace(
            $input,
            $definition['fixed']
        );

        $this->assertOperationAllowed(
            $operation
        );

        $service = $this->loadService(
            $definition['service']
        );

        $arguments = $this->arguments(
            $definition['shape'],
            $input,
            $uploads
        );

        $handle = fn(): array =>
            $service->{$definition['handler']}(
                ...$arguments
            );

        // Login owns its transaction so failed-attempt counters still commit.
        if ($operation === 'auth.login') {
            return $handle();
        }

        $needsTransaction =
            $mutation
            || $operation === 'files.download';

        return $needsTransaction
            ? $this->ctx->db->transaction($handle)
            : $handle();
    }

    private function assertOperationAllowed(
        string $operation
    ): void {
        $publicOperations = [
            'session',
            'auth.login',
        ];

        if (
            !in_array(
                $operation,
                $publicOperations,
                true
            )
            && !$this->ctx->id()
        ) {
            throw new Problem(
                'Your session ended. Sign in again.',
                401
            );
        }

        if (
            $operation === 'auth.login'
            && $this->ctx->id()
        ) {
            throw new Problem(
                'Sign out before signing in as another user.',
                409
            );
        }

        $mustChangePassword =
            $this->ctx->id()
            && (int) $this->ctx->user['require_password_change'];

        $passwordOperations = [
            'session',
            'auth.password',
            'auth.logout',
        ];

        if (
            $mustChangePassword
            && !in_array(
                $operation,
                $passwordOperations,
                true
            )
        ) {
            throw new Problem(
                'Change your initial password before using the system.',
                423
            );
        }
    }

    private function loadService(string $class): object
    {
        if (!function_exists('get_instance')) {
            return new $class($this->ctx);
        }

        $CI = get_instance();
        $alias = 'dts_' . strtolower($class);

        $CI->load->library(
            $class,
            ['context' => $this->ctx],
            $alias
        );

        return $CI->$alias;
    }

    private function arguments(
        string $shape,
        array $input,
        array $uploads
    ): array {
        return match ($shape) {
            'none' => [],
            'input' => [$input],
            'module_input' => [
                Rules::text(
                    $input,
                    'module',
                    50
                ),
                $input,
            ],
            'module_id' => [
                Rules::text(
                    $input,
                    'module',
                    50
                ),
                Rules::id($input),
            ],
            'domain_input' => [
                Rules::choice(
                    $input,
                    'domain',
                    ['softcopy', 'hardcopy']
                ),
                $input,
            ],
            'upload' => [
                $uploads['file'] ?? [],
            ],
            'id' => [
                Rules::id($input),
            ],
            default => throw new \LogicException(
                'Invalid endpoint configuration.'
            ),
        };
    }

    public static function respond(
        string $path,
        array $routeParameters = []
    ): void {
        Security::startSession();
        Security::headers();

        try {
            $method =
                $_SERVER['REQUEST_METHOD']
                ?? 'GET';

            $input = self::parseInput(
                $method
            );

            $definition =
                (new Endpoint_registry())
                    ->byPath($path);

            $input = array_replace(
                $input,
                $definition['fixed'],
                $routeParameters
            );

            $data = (new self())->execute(
                $definition,
                $method,
                $input,
                $_FILES
            );

            if ($definition['op'] === 'files.download') {
                self::download($data);
                return;
            }

            self::json(
                [
                    'ok' => true,
                    'data' => $data,
                    'csrf' => $_SESSION['csrf'],
                ]
            );
        } catch (Problem $e) {
            http_response_code($e->status);

            self::json(
                [
                    'ok' => false,
                    'error' => [
                        'message' => $e->getMessage(),
                        'fields' => $e->fields,
                    ],
                    'csrf' => $_SESSION['csrf'] ?? '',
                ]
            );
        } catch (Database_error $e) {
            self::databaseError($e);
        } catch (\Throwable $e) {
            self::unexpectedError($e);
        }
    }

    private static function parseInput(
        string $method
    ): array {
        $isJson =
            $method === 'POST'
            && str_contains(
                $_SERVER['CONTENT_TYPE'] ?? '',
                'application/json'
            );

        if (!$isJson) {
            return $method === 'POST'
                ? $_POST
                : $_GET;
        }

        $maxBytes = 1024 * 1024;

        if (
            (int) (
                $_SERVER['CONTENT_LENGTH']
                ?? 0
            ) > $maxBytes
        ) {
            throw new Problem(
                'Form payload is too large.',
                413
            );
        }

        $raw = file_get_contents(
            'php://input',
            false,
            null,
            0,
            $maxBytes + 1
        );

        if (strlen($raw) > $maxBytes) {
            throw new Problem(
                'Form payload is too large.',
                413
            );
        }

        return Rules::json($raw);
    }

    private static function download(array $data): void
    {
        session_write_close();

        header(
            'Content-Type: ' . $data['mime']
        );

        header(
            'Content-Length: ' . $data['size']
        );

        header(
            'Content-Disposition: attachment; filename="document"; ' .
            "filename*=UTF-8''" .
            rawurlencode($data['name'])
        );

        readfile($data['path']);
    }

    private static function databaseError(
        Database_error $error
    ): void {
        $conflict = $error->isConflict();

        error_log(
            'PK database error ' .
            $error->getMessage()
        );

        http_response_code(
            $conflict ? 409 : 503
        );

        self::json(
            [
                'ok' => false,
                'error' => [
                    'message' => $conflict
                        ? 'A duplicate, referenced record, or concurrent change prevented this action. Refresh and check your selections.'
                        : 'Database unavailable or not installed. Ask the administrator to check the private server logs and installation.',
                    'fields' => [],
                ],
                'csrf' => $_SESSION['csrf'] ?? '',
            ]
        );
    }

    private static function unexpectedError(
        \Throwable $error
    ): void {
        $reference = bin2hex(
            random_bytes(5)
        );

        error_log(
            'PK error ' .
            $reference .
            ': ' .
            $error
        );

        http_response_code(500);

        self::json(
            [
                'ok' => false,
                'error' => [
                    'message' =>
                        'The operation could not be completed. Reference: ' .
                        $reference,
                    'fields' => [],
                ],
                'csrf' => $_SESSION['csrf'] ?? '',
            ]
        );
    }

    private static function json(array $data): void
    {
        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo Context::json($data);
    }
}
