<?php
declare(strict_types=1);
namespace Pk\Core;

/** One CI3 MySQLi connection and transaction boundary; models use its Query Builder. */
final class Database
{
    // Kani ang shared DB wrapper; transaction ug error handling diri para one behavior ra tanan models.
    private bool $active = false;
    private bool $rollbackOnly = false;
    public \CI_DB_query_builder $builder;

    public function __construct(\CI_DB_query_builder $builder)
    {
        $this->builder=$builder;
        if ($builder->dbdriver !== 'mysqli') throw new \LogicException('DTS requires the CodeIgniter mysqli driver.');
        $builder->db_debug = false;
        // Failed transaction groups can be rolled back and a later group can start cleanly.
        $builder->trans_strict(false);
    }

    public static function connect(): self
    {
        if (!extension_loaded('mysqli')) throw new Database_error('Enable the mysqli PHP extension.');
        if (function_exists('get_instance')) {
            $CI = get_instance();
            $CI->load->database();
            $driver = $CI->db;
        } else {
            require_once PK_ROOT.'/application/cli_bootstrap.php';
            require PK_ROOT.'/application/config/database.php';
            $driver = DB($db['default'], true);
        }
        if (!$driver->conn_id) throw new Database_error('Could not connect to the configured MySQL database.');
        $database = new self($driver);
        $database->query("SET time_zone = '+08:00'");
        return $database;
    }

    public function checked(mixed $result): mixed
    {
        if ($result === false) {
            if ($this->active) $this->rollbackOnly = true;
            $error = $this->builder->error();
            $this->builder->reset_query();
            throw new Database_error((string)($error['message'] ?? 'Database operation failed.'), (int)($error['code'] ?? 0));
        }
        return $result;
    }

    /** CI3-bound SQL for DDL, savepoints, FOR UPDATE, and the two atomic upserts. */
    public function query(string $sql, array $params = []): mixed
    {
        return $this->checked($this->builder->query($sql, $params));
    }

    public function rows(mixed $result): array
    {
        $result = $this->checked($result);
        if (!($result instanceof \CI_DB_result)) throw new \LogicException('Expected a CI3 query result.');
        $rows = $result->result_array();
        // MySQLi text queries can return integer columns as strings. Preserve the API's
        // numeric IDs without changing text identifiers such as "000123" or "007".
        $integerFields = [];
        foreach ($result->field_data() as $field) {
            $type=$field->type;
            $integerType=is_string($type)
                ? in_array(strtolower($type), ['tinyint','smallint','mediumint','int','bigint','year'], true)
                : (is_int($type) && in_array($type,[1,2,3,8,9,13],true));
            if ($integerType) $integerFields[]=$field->name;
        }
        foreach ($rows as &$row) foreach ($integerFields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $integer = filter_var($row[$field], FILTER_VALIDATE_INT);
                if ($integer !== false) $row[$field] = $integer;
            }
        }
        unset($row);
        $result->free_result();
        return $rows;
    }

    public function first(mixed $result): ?array { return $this->rows($result)[0] ?? null; }
    // Kept for CLI verification queries; application models use $this->db->get().
    public function one(string $sql, array $params = []): ?array { return $this->first($this->query($sql, $params)); }
    public function all(string $sql, array $params = []): array { return $this->rows($this->query($sql, $params)); }

    public function in_transaction(): bool { return $this->active; }
    public function require_transaction(): void
    {
        if (!$this->active) { $this->builder->reset_query(); throw new \LogicException('A row lock requires an active transaction.'); }
    }
    public function begin(): void
    {
        if ($this->active) throw new \LogicException('The transaction is already active.');
        $this->checked($this->builder->trans_begin());
        $this->active = true;
        $this->rollbackOnly = false;
    }
    public function commit(): void
    {
        $this->require_transaction();
        if ($this->rollbackOnly || !$this->builder->trans_status()) {
            throw new Database_error('A failed operation marked this transaction for rollback.');
        }
        $this->checked($this->builder->trans_commit());
        $this->active = false;
    }
    public function rollback(): void
    {
        if (!$this->active) return;
        try {
            if (!$this->builder->trans_status()) {
                // CI3 trans_complete resets a failed group when trans_strict is false.
                $this->builder->trans_complete();
            } else $this->checked($this->builder->trans_rollback());
        } finally {
            $this->active = false; $this->rollbackOnly = false; $this->builder->reset_query();
        }
    }
    public function transaction(callable $work): mixed
    {
        if ($this->active) {
            try { return $work(); }
            catch (\Throwable $e) { $this->rollbackOnly = true; throw $e; }
        }
        $this->begin();
        try { $result = $work(); $this->commit(); return $result; }
        catch (\Throwable $e) { $this->rollback(); throw $e; }
    }

    public static function identifier(string $value): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $value)) throw new \LogicException('Unsafe model identifier.');
        return $value;
    }
    public static function mutable(string $table): void
    {
        if (in_array($table, ['audit_logs','status_history','workflow_history'], true)) throw new \LogicException('Append-only table.');
    }
    public function insert(string $table, array $data): int
    {
        self::identifier($table);
        foreach (array_keys($data) as $column) self::identifier($column);
        $this->builder->reset_query();
        $this->checked($this->builder->insert($table, $data));
        return (int)$this->builder->insert_id();
    }
    public function update(string $table, int $id, array $data, bool $versioned = true): void
    {
        self::identifier($table); self::mutable($table);
        foreach (array_keys($data) as $column) self::identifier($column);
        $this->builder->reset_query()->where('id', $id)->set($data);
        if ($versioned) $this->builder->set('version', 'version + 1', false);
        $this->checked($this->builder->update($table));
    }
    public function row(string $table, int $id, bool $lock = false): array
    {
        self::identifier($table);
        $this->builder->reset_query()->from($table)->where('id', $id)->limit(1);
        $result = $lock ? $this->locked_select() : $this->builder->get();
        return $this->first($result) ?? throw new Problem('Record not found.', 404);
    }
    public function lock(string $table, int $id, ?int $version = null): array
    {
        $this->require_transaction();
        $row = $this->row($table, $id, true);
        if ($version !== null && (int)($row['version'] ?? 0) !== $version) throw new Problem('This record changed. Close the dialog, refresh and try again.', 409);
        return $row;
    }
    public function locked_select(): mixed
    {
        $this->require_transaction();
        // CI3 Query Builder has no lockForUpdate(). Only append this fixed clause to
        // SQL compiled (and values escaped) by the real Query Builder.
        $sql = $this->builder->get_compiled_select();
        return $this->query($sql.' FOR UPDATE');
    }
}
