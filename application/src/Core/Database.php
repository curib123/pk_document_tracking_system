<?php
declare(strict_types=1);
namespace Pk\Core;
use PDO;
final class Database
{
    public function __construct(public readonly PDO $pdo) { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); }
    public static function connect(): self
    {
        $host=getenv('DB_HOST') ?: '127.0.0.1'; $port=getenv('DB_PORT') ?: '3306'; $name=getenv('DB_DATABASE') ?: 'pk_dts';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new \RuntimeException('Invalid database name.');
        $db = new self(new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", getenv('DB_USERNAME') ?: '', getenv('DB_PASSWORD') ?: ''));
        $db->query("SET time_zone = '+08:00'");
        return $db;
    }
    public function query(string $sql, array $params=[]): \PDOStatement { $s=$this->pdo->prepare($sql); $s->execute($params); return $s; }
    public function one(string $sql, array $params=[]): ?array { return $this->query($sql,$params)->fetch() ?: null; }
    public function all(string $sql, array $params=[]): array { return $this->query($sql,$params)->fetchAll(); }
    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) return $callback();
        $this->pdo->beginTransaction();
        try { $result=$callback(); $this->pdo->commit(); return $result; }
        catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    private function identifier(string $name): string { if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) throw new \LogicException('Unsafe SQL identifier.'); return '`'.$name.'`'; }
    public function insert(string $table, array $data): int
    {
        $columns=implode(',',array_map($this->identifier(...),array_keys($data)));
        $this->query('INSERT INTO '.$this->identifier($table).' ('.$columns.') VALUES ('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));
        return (int)$this->pdo->lastInsertId();
    }
    public function update(string $table, int $id, array $data, bool $versioned=true): void
    {
        if (in_array($table,['audit_logs','status_history','workflow_history'],true)) throw new \LogicException('Append-only table.');
        $parts=array_map(fn($key)=>$this->identifier($key).'=?',array_keys($data));
        if ($versioned) $parts[]='version=version+1';
        $this->query('UPDATE '.$this->identifier($table).' SET '.implode(',',$parts).' WHERE id=?',[...array_values($data),$id]);
    }
    public function row(string $table, int $id, bool $lock=false): array
    {
        return $this->one('SELECT * FROM '.$this->identifier($table).' WHERE id=?'.($lock?' FOR UPDATE':''),[$id]) ?? throw new Problem('Record not found.',404);
    }
    public function lock(string $table, int $id, ?int $version=null): array
    {
        if (!$this->pdo->inTransaction()) throw new \LogicException('A row lock requires a transaction.');
        $row=$this->row($table,$id,true);
        if ($version !== null && (int)($row['version'] ?? 0) !== $version) throw new Problem('This record changed. Close the dialog, refresh and try again.',409);
        return $row;
    }
}
