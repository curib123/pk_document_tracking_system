<?php
declare(strict_types=1);

use Pk\Core\{Context, Database};

/**
 * Shared persistence mechanics.
 * Each domain model owns its own Query Builder queries.
 */
abstract class Repository_model extends CI_Model
{
    // Shared DB mechanics ra ni; actual queries naa sa specific model para dili maglibog.
    protected Context $ctx;
    protected Database $store;

    public CI_DB_query_builder $db;

    public function __construct()
    {
        parent::__construct();

        if (function_exists('get_instance')) {
            $CI = get_instance();
            $CI->load->database();

            $this->db = $CI->db;
        }
    }

    public function initialize(Context $ctx): void
    {
        $this->ctx = $ctx;
        $this->store = $ctx->db;
        $this->db = $ctx->db->builder;
    }

    protected function first(
        bool $lock = false
    ): ?array {
        $result = $lock
            ? $this->store->locked_select()
            : $this->db->get();

        return $this->store->first(
            $result
        );
    }

    protected function results(
        bool $lock = false
    ): array {
        $result = $lock
            ? $this->store->locked_select()
            : $this->db->get();

        return $this->store->rows(
            $result
        );
    }

    protected function written(mixed $result): bool
    {
        $this->store->checked($result);
        return true;
    }

    public function transaction(callable $work): mixed
    {
        return $this->store->transaction(
            $work
        );
    }

    public function insert(
        string $table,
        array $data
    ): int {
        return $this->store->insert(
            $table,
            $data
        );
    }

    public function update(
        string $table,
        int $id,
        array $data,
        bool $versioned = true
    ): void {
        $this->store->update(
            $table,
            $id,
            $data,
            $versioned
        );
    }

    public function row(
        string $table,
        int $id,
        bool $lock = false
    ): array {
        return $this->store->row(
            $table,
            $id,
            $lock
        );
    }

    public function lock(
        string $table,
        int $id,
        ?int $version = null
    ): array {
        return $this->store->lock(
            $table,
            $id,
            $version
        );
    }
}
