<?php

declare(strict_types=1);

namespace Aidat\Core;

use Closure;

/** Migrasyonların kullandığı şema yüzeyi. */
final class Schema
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(string $table, Closure $definition): void
    {
        $blueprint = new Blueprint($table);
        $definition($blueprint);
        foreach ($blueprint->toSql($this->db->driver()) as $sql) {
            $this->db->pdo()->exec($sql);
        }
    }

    public function table(string $table, Closure $definition): void
    {
        $blueprint = new Blueprint($table);
        $definition($blueprint);
        foreach ($blueprint->toAlterSql($this->db->driver()) as $sql) {
            $this->db->pdo()->exec($sql);
        }
    }

    public function drop(string $table): void
    {
        $this->db->pdo()->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    public function raw(string $sql): void
    {
        $this->db->pdo()->exec($sql);
    }

    public function db(): Database
    {
        return $this->db;
    }
}
