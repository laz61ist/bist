<?php

declare(strict_types=1);

namespace Aidat\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * PDO sarmalayıcı. Tüm sorgular prepared statement ile çalışır.
 * Sürücüler: sqlite (varsayılan, sıfır kurulum) ve mysql (MariaDB/MySQL).
 */
final class Database
{
    private ?PDO $pdo = null;
    private readonly string $driver;
    private int $transactionDepth = 0;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
        $driver = (string) ($config['driver'] ?? 'sqlite');
        if (!in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException("Desteklenmeyen veritabanı sürücüsü: {$driver}");
        }
        $this->driver = $driver;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if ($this->driver === 'sqlite') {
            $path = (string) $this->config['sqlite_path'];
            if ($path !== ':memory:') {
                $dir = dirname($path);
                if (!is_dir($dir) && !mkdir($dir, 0o770, true) && !is_dir($dir)) {
                    throw new RuntimeException("Veri dizini oluşturulamadı: {$dir}");
                }
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            if ($path !== ':memory:') {
                $pdo->exec('PRAGMA journal_mode = WAL');
                $pdo->exec('PRAGMA synchronous = NORMAL');
            }
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['host'] ?? '127.0.0.1',
                (int) ($this->config['port'] ?? 3306),
                $this->config['name'] ?? 'aidat',
                $this->config['charset'] ?? 'utf8mb4',
            );
            $pdo = new PDO($dsn, (string) ($this->config['user'] ?? ''), (string) ($this->config['pass'] ?? ''), $options);
            $pdo->exec("SET sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
            $pdo->exec("SET time_zone = '+03:00'");
        }

        return $this->pdo = $pdo;
    }

    /** @param array<int|string, mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
                $type = match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_INT,
                    $value === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };
                $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
            }
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage() . ' | SQL: ' . $sql, (int) $e->getCode(), $e);
        }
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<int|string, mixed> $params */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn($column);
        return $value === false ? null : $value;
    }

    /** @param array<int|string, mixed> $params */
    public function fetchInt(string $sql, array $params = []): int
    {
        return (int) ($this->fetchColumn($sql, $params) ?? 0);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int|string, mixed> key => value
     */
    public function fetchPairs(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdent($table),
            implode(', ', array_map(fn (string $c) => $this->quoteIdent($c), $columns)),
            implode(', ', array_map(fn (string $c) => ':' . $c, $columns)),
        );
        $this->run($sql, $data);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $params
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = [];
        $bind = [];
        foreach ($data as $column => $value) {
            $sets[] = $this->quoteIdent($column) . ' = :set_' . $column;
            $bind['set_' . $column] = $value;
        }
        foreach ($params as $key => $value) {
            $bind[is_int($key) ? 'w' . $key : $key] = $value;
        }
        if (array_is_list($params)) {
            // Konumsal ? parametrelerini isimli hale çevir
            $i = 0;
            $where = preg_replace_callback('/\?/', function () use (&$i): string {
                return ':w' . ($i++);
            }, $where) ?? $where;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->quoteIdent($table), implode(', ', $sets), $where);
        return $this->run($sql, $bind)->rowCount();
    }

    /** @param array<int|string, mixed> $params */
    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->run(sprintf('DELETE FROM %s WHERE %s', $this->quoteIdent($table), $where), $params)->rowCount();
    }

    /** @template T @param callable(Database): T $fn @return T */
    public function transaction(callable $fn): mixed
    {
        $pdo = $this->pdo();
        if ($this->transactionDepth === 0) {
            $pdo->beginTransaction();
        }
        $this->transactionDepth++;
        try {
            $result = $fn($this);
            $this->transactionDepth--;
            if ($this->transactionDepth === 0) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            $this->transactionDepth--;
            if ($this->transactionDepth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    public function tableExists(string $table): bool
    {
        if ($this->isSqlite()) {
            return $this->fetch("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]) !== null;
        }
        return $this->fetch('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]) !== null;
    }

    public function quoteIdent(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /** Şimdiki zaman, DB biçiminde. */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function ping(): bool
    {
        try {
            return $this->fetchColumn('SELECT 1') !== null;
        } catch (Throwable) {
            return false;
        }
    }
}
