<?php
declare(strict_types=1);

namespace Pk\Core;

/**
 * One CI3 MySQLi connection and transaction boundary.
 * Models use this shared Query Builder instance.
 */
final class Database
{
    // Transaction ug DB error behavior centralized diri para consistent tanan models.
    private bool $active = false;
    private bool $rollbackOnly = false;

    public \CI_DB_query_builder $builder;

    public function __construct(
        \CI_DB_query_builder $builder
    ) {
        $this->builder = $builder;

        if ($builder->dbdriver !== 'mysqli') {
            throw new \LogicException(
                'DTS requires the CodeIgniter mysqli driver.'
            );
        }

        $builder->db_debug = false;

        // Failed groups can rollback cleanly and the next transaction can continue.
        $builder->trans_strict(false);
    }

    public static function connect(): self
    {
        if (!extension_loaded('mysqli')) {
            throw new Database_error(
                'Enable the mysqli PHP extension.'
            );
        }

        if (function_exists('get_instance')) {
            $CI = get_instance();
            $CI->load->database();
            $driver = $CI->db;
        } else {
            require_once
                PK_ROOT
                . '/application/cli_bootstrap.php';

            require
                PK_ROOT
                . '/application/config/database.php';

            $driver = DB(
                $db['default'],
                true
            );
        }

        if (!$driver->conn_id) {
            throw new Database_error(
                'Could not connect to the configured MySQL database.'
            );
        }

        $database = new self($driver);

        $database->query(
            "SET time_zone = '+08:00'"
        );

        return $database;
    }

    public function checked(mixed $result): mixed
    {
        if ($result !== false) {
            return $result;
        }

        if ($this->active) {
            $this->rollbackOnly = true;
        }

        $error = $this->builder->error();
        $this->builder->reset_query();

        throw new Database_error(
            (string) (
                $error['message']
                ?? 'Database operation failed.'
            ),
            (int) (
                $error['code']
                ?? 0
            )
        );
    }

    /**
     * Raw SQL is limited to DDL, locks, savepoints, and atomic DB primitives.
     */
    public function query(
        string $sql,
        array $params = []
    ): mixed {
        return $this->checked(
            $this->builder->query(
                $sql,
                $params
            )
        );
    }

    public function rows(mixed $result): array
    {
        $result = $this->checked($result);

        if (!($result instanceof \CI_DB_result)) {
            throw new \LogicException(
                'Expected a CI3 query result.'
            );
        }

        $rows = $result->result_array();
        $integerFields = [];

        // MySQLi can return integer columns as strings, so normalize only true numeric DB fields.
        foreach ($result->field_data() as $field) {
            if ($this->isIntegerFieldType($field->type)) {
                $integerFields[] = $field->name;
            }
        }

        foreach ($rows as &$row) {
            foreach ($integerFields as $field) {
                if (
                    !isset($row[$field])
                    || !is_string($row[$field])
                ) {
                    continue;
                }

                $integer = filter_var(
                    $row[$field],
                    FILTER_VALIDATE_INT
                );

                if ($integer !== false) {
                    $row[$field] = $integer;
                }
            }
        }
        unset($row);

        $result->free_result();

        return $rows;
    }

    private function isIntegerFieldType(
        mixed $type
    ): bool {
        if (is_string($type)) {
            return in_array(
                strtolower($type),
                [
                    'tinyint',
                    'smallint',
                    'mediumint',
                    'int',
                    'bigint',
                    'year',
                ],
                true
            );
        }

        // PHP 8.0 MySQLi can expose native numeric type codes.
        return is_int($type)
            && in_array(
                $type,
                [1, 2, 3, 8, 9, 13],
                true
            );
    }

    public function first(mixed $result): ?array
    {
        return $this->rows($result)[0] ?? null;
    }

    /**
     * CLI verification helper. App models normally use Query Builder directly.
     */
    public function one(
        string $sql,
        array $params = []
    ): ?array {
        return $this->first(
            $this->query(
                $sql,
                $params
            )
        );
    }

    public function all(
        string $sql,
        array $params = []
    ): array {
        return $this->rows(
            $this->query(
                $sql,
                $params
            )
        );
    }

    public function in_transaction(): bool
    {
        return $this->active;
    }

    public function require_transaction(): void
    {
        if ($this->active) {
            return;
        }

        $this->builder->reset_query();

        throw new \LogicException(
            'A row lock requires an active transaction.'
        );
    }

    public function begin(): void
    {
        if ($this->active) {
            throw new \LogicException(
                'The transaction is already active.'
            );
        }

        $this->checked(
            $this->builder->trans_begin()
        );

        $this->active = true;
        $this->rollbackOnly = false;
    }

    public function commit(): void
    {
        $this->require_transaction();

        $failed =
            $this->rollbackOnly
            || !$this->builder->trans_status();

        if ($failed) {
            throw new Database_error(
                'A failed operation marked this transaction for rollback.'
            );
        }

        $this->checked(
            $this->builder->trans_commit()
        );

        $this->active = false;
    }

    public function rollback(): void
    {
        if (!$this->active) {
            return;
        }

        try {
            if (!$this->builder->trans_status()) {
                // CI3 resets a failed group through trans_complete when strict mode is off.
                $this->builder->trans_complete();
            } else {
                $this->checked(
                    $this->builder->trans_rollback()
                );
            }
        } finally {
            $this->active = false;
            $this->rollbackOnly = false;
            $this->builder->reset_query();
        }
    }

    public function transaction(callable $work): mixed
    {
        if ($this->active) {
            try {
                return $work();
            } catch (\Throwable $e) {
                $this->rollbackOnly = true;
                throw $e;
            }
        }

        $this->begin();

        try {
            $result = $work();
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public static function identifier(string $value): string
    {
        if (
            !preg_match(
                '/^[a-z_][a-z0-9_]*$/',
                $value
            )
        ) {
            throw new \LogicException(
                'Unsafe model identifier.'
            );
        }

        return $value;
    }

    public static function mutable(string $table): void
    {
        if (
            in_array(
                $table,
                [
                    'status_history',
                    'workflow_history',
                ],
                true
            )
        ) {
            throw new \LogicException(
                'Append-only table.'
            );
        }
    }

    public function insert(
        string $table,
        array $data
    ): int {
        self::identifier($table);

        foreach (array_keys($data) as $column) {
            self::identifier($column);
        }

        $this->builder->reset_query();

        $this->checked(
            $this->builder->insert(
                $table,
                $data
            )
        );

        return (int) $this->builder->insert_id();
    }

    public function update(
        string $table,
        int $id,
        array $data,
        bool $versioned = true
    ): void {
        self::identifier($table);
        self::mutable($table);

        foreach (array_keys($data) as $column) {
            self::identifier($column);
        }

        $this->builder
            ->reset_query()
            ->where('id', $id)
            ->set($data);

        if ($versioned) {
            $this->builder->set(
                'version',
                'version + 1',
                false
            );
        }

        $this->checked(
            $this->builder->update($table)
        );
    }

    public function row(
        string $table,
        int $id,
        bool $lock = false
    ): array {
        self::identifier($table);

        $this->builder
            ->reset_query()
            ->from($table)
            ->where('id', $id)
            ->limit(1);

        $result = $lock
            ? $this->locked_select()
            : $this->builder->get();

        return $this->first($result)
            ?? throw new Problem(
                'Record not found.',
                404
            );
    }

    public function lock(
        string $table,
        int $id,
        ?int $version = null
    ): array {
        $this->require_transaction();

        $row = $this->row(
            $table,
            $id,
            true
        );

        $stale =
            $version !== null
            && (int) ($row['version'] ?? 0) !== $version;

        if ($stale) {
            throw new Problem(
                'This record changed. Close the dialog, refresh and try again.',
                409
            );
        }

        return $row;
    }

    public function locked_select(): mixed
    {
        $this->require_transaction();

        // Query Builder escapes values first; fixed FOR UPDATE ra atong i-append.
        $sql = $this->builder
            ->get_compiled_select();

        return $this->query(
            $sql . ' FOR UPDATE'
        );
    }
}
