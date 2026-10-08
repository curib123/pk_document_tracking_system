<?php
declare(strict_types=1);

namespace Pk\Core;

final class Context
{
    // Mao ni ang request context: current user, permissions, DB, audit, ug notifications in one place.
    public ?array $user = null;
    public Database $db;

    private array $models = [];
    private array $permissions = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * One context owns all models for one request/transaction.
     */
    public function model(string $class): \Repository_model
    {
        if (!is_subclass_of($class, \Repository_model::class)) {
            throw new \LogicException(
                'Invalid domain model.'
            );
        }

        if (!isset($this->models[$class])) {
            $model = $this->loadModel($class);
            $model->initialize($this);

            $this->models[$class] = $model;
        }

        return $this->models[$class];
    }

    private function loadModel(string $class): \Repository_model
    {
        if (!function_exists('get_instance')) {
            return new $class();
        }

        $CI = get_instance();
        $alias = 'dts_' . strtolower($class);

        $CI->load->model(
            $class,
            $alias
        );

        return $CI->$alias;
    }

    public static function fromOptions(
        self|array|null $options
    ): self {
        if ($options instanceof self) {
            return $options;
        }

        if (
            is_array($options)
            && ($options['context'] ?? null) instanceof self
        ) {
            return $options['context'];
        }

        throw new \LogicException(
            'Load the service with the current request context.'
        );
    }

    public function identify(int $id): void
    {
        $user = $this
            ->model(\Identity_model::class)
            ->active_user([$id]);

        if (!$user) {
            throw new Problem(
                'Your account is inactive or no longer available.',
                401
            );
        }

        $this->user = $user;

        $capabilities = $this
            ->model(\Identity_model::class)
            ->role_capabilities(
                [$user['role_id']]
            );

        $this->permissions = array_column(
            $capabilities,
            'capability'
        );
    }

    public function id(): int
    {
        return (int) (
            $this->user['id'] ?? 0
        );
    }

    public function can(string $permission): bool
    {
        return in_array(
            $permission,
            $this->permissions,
            true
        );
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function require(string $permission): void
    {
        if (!$this->id()) {
            throw new Problem(
                'Sign in first.',
                401
            );
        }

        if (!$this->can($permission)) {
            throw new Problem(
                'You do not have permission for this action.',
                403
            );
        }
    }

    public function safeUser(): ?array
    {
        if (!$this->user) {
            return null;
        }

        $user = $this->user;

        unset(
            $user['password_hash'],
            $user['session_version']
        );

        return $user;
    }

    public static function name(array $user): string
    {
        return trim(
            ($user['first_name'] ?? '')
            . ' '
            . ($user['middle_name'] ?? '')
            . ' '
            . ($user['last_name'] ?? '')
        );
    }

    public static function json(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );
    }

    public function audit(
        string $module,
        string $action,
        ?int $entity = null,
        mixed $before = null,
        mixed $after = null,
        ?string $reason = null,
        ?int $request = null
    ): void {
        // Audit trail is file-backed JSONL, dili DB table, para append-only ug light sa database.
        $before = self::redact($before);
        $after = self::redact($after);

        $directory = PK_ROOT . '/storage/audit';

        if (
            !is_dir($directory)
            && !mkdir($directory, 0770, true)
            && !is_dir($directory)
        ) {
            throw new \RuntimeException(
                'Unable to create the audit log directory.'
            );
        }

        $path =
            $directory .
            '/audit-' .
            date('Y-m') .
            '.jsonl';

        $record = [
            'id' =>
                ((int) round(
                    microtime(true) * 1000000
                )),
            'user_id' => $this->id() ?: null,
            'username' =>
                $this->user['username']
                ?? 'anonymous',
            'role_name' =>
                $this->user['role_name']
                ?? '',
            'module' => $module,
            'action' => $action,
            'entity_id' => $entity,
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $reason,
            'request_id' => $request,
            'http_method' => substr(
                $_SERVER['REQUEST_METHOD']
                    ?? 'CLI',
                0,
                10
            ),
            'path' => substr(
                $_SERVER['REQUEST_URI']
                    ?? 'CLI',
                0,
                255
            ),
            'ip_address' => substr(
                $_SERVER['REMOTE_ADDR']
                    ?? 'local',
                0,
                64
            ),
            'user_agent' => substr(
                $_SERVER['HTTP_USER_AGENT']
                    ?? 'CLI',
                0,
                255
            ),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $line = self::json($record) . PHP_EOL;

        if (
            file_put_contents(
                $path,
                $line,
                FILE_APPEND | LOCK_EX
            ) === false
        ) {
            throw new \RuntimeException(
                'Unable to write the audit log.'
            );
        }
    }

    private static function redact(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            $sensitive = preg_match(
                '/password|csrf|session|token|storage_name/i',
                (string) $key
            );

            if ($sensitive) {
                unset($data[$key]);
                continue;
            }

            $data[$key] = self::redact($value);
        }

        return $data;
    }

    public function notify(
        int $userId,
        string $title,
        string $message,
        ?int $requestId = null
    ): void {
        $this
            ->model(\Identity_model::class)
            ->insert(
                'notifications',
                [
                    'user_id' => $userId,
                    'title' => $title,
                    'message' => $message,
                    'request_id' => $requestId,
                ]
            );
    }

    public function sequence(
        string $key,
        string $prefix
    ): string {
        $model = $this->model(
            \Identity_model::class
        );

        $model->increment_sequence([$key]);

        $row = $model->sequence_for_update(
            [$key]
        );

        return $prefix
            . str_pad(
                (string) $row['value'],
                6,
                '0',
                STR_PAD_LEFT
            );
    }

    public function active(
        string $table,
        int $id
    ): array {
        $row = $this->db->row(
            $table,
            $id,
            true
        );

        if (
            isset($row['active'])
            && !(int) $row['active']
        ) {
            throw new Problem(
                'Selected ' .
                $table .
                ' record is inactive.'
            );
        }

        if ($table === 'users') {
            $role = $this->db->row(
                'roles',
                (int) $row['role_id']
            );

            if (!(int) $role['active']) {
                throw new Problem(
                    'The selected user has an inactive role.'
                );
            }
        }

        return $row;
    }

    public function status(
        string $domain,
        int $id,
        string $previous,
        string $next,
        string $action,
        string $remarks = ''
    ): void {
        $this
            ->model(\Identity_model::class)
            ->insert(
                'status_history',
                [
                    'domain' => $domain,
                    'document_id' => $id,
                    'previous_status' => $previous,
                    'new_status' => $next,
                    'action' => $action,
                    'user_id' => $this->id(),
                    'remarks' => $remarks,
                ]
            );
    }
}
