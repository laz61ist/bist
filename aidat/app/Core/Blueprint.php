<?php

declare(strict_types=1);

namespace Aidat\Core;

/** Tablo tanımı; sürücüye göre DDL üretir (sqlite / mysql). */
final class Blueprint
{
    /** @var list<array<string, mixed>> */
    private array $columns = [];
    /** @var list<array{type: string, columns: list<string>, name: string}> */
    private array $indexes = [];
    /** @var list<array{column: string, table: string, on_delete: string}> */
    private array $foreigns = [];
    private ?string $current = null;

    public function __construct(public readonly string $table)
    {
    }

    private function add(string $name, string $type, array $extra = []): self
    {
        $this->columns[] = array_merge([
            'name' => $name,
            'type' => $type,
            'nullable' => false,
            'default' => null,
            'has_default' => false,
            'unique' => false,
            'length' => null,
        ], $extra);
        $this->current = $name;
        return $this;
    }

    public function id(string $name = 'id'): self
    {
        return $this->add($name, 'id');
    }

    public function integer(string $name): self
    {
        return $this->add($name, 'integer');
    }

    public function bigInteger(string $name): self
    {
        return $this->add($name, 'bigint');
    }

    /** Para: tam sayı KURUŞ olarak saklanır (kayan nokta yok). */
    public function money(string $name): self
    {
        return $this->add($name, 'bigint')->default(0);
    }

    public function float(string $name): self
    {
        return $this->add($name, 'float');
    }

    public function string(string $name, int $length = 255): self
    {
        return $this->add($name, 'string', ['length' => $length]);
    }

    public function text(string $name): self
    {
        return $this->add($name, 'text');
    }

    public function boolean(string $name): self
    {
        return $this->add($name, 'boolean')->default(0);
    }

    public function date(string $name): self
    {
        return $this->add($name, 'date');
    }

    public function datetime(string $name): self
    {
        return $this->add($name, 'datetime');
    }

    public function json(string $name): self
    {
        return $this->add($name, 'text');
    }

    public function timestamps(): self
    {
        $this->datetime('created_at')->nullable();
        $this->datetime('updated_at')->nullable();
        return $this;
    }

    public function nullable(): self
    {
        $this->columns[array_key_last($this->columns)]['nullable'] = true;
        return $this;
    }

    public function default(mixed $value): self
    {
        $last = array_key_last($this->columns);
        $this->columns[$last]['default'] = $value;
        $this->columns[$last]['has_default'] = true;
        return $this;
    }

    public function unique(?array $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            $this->columns[array_key_last($this->columns)]['unique'] = true;
            return $this;
        }
        $this->indexes[] = ['type' => 'unique', 'columns' => $columns, 'name' => $name ?? $this->table . '_' . implode('_', $columns) . '_uq'];
        return $this;
    }

    public function index(array|string $columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $name ??= $this->table . '_' . implode('_', $columns) . '_idx';
        foreach ($this->indexes as $existing) {
            if ($existing['name'] === $name) {
                return $this; // aynı indeks iki kez tanımlanmışsa yoksay
            }
        }
        $this->indexes[] = ['type' => 'index', 'columns' => $columns, 'name' => $name];
        return $this;
    }

    /** Yabancı anahtar: son eklenen (veya verilen) sütun → tablo(id). */
    public function references(string $table, string $onDelete = 'RESTRICT', ?string $column = null): self
    {
        $column ??= $this->current ?? '';
        $this->foreigns[] = ['column' => $column, 'table' => $table, 'on_delete' => strtoupper($onDelete)];
        $this->index([$column]);
        return $this;
    }

    /** @return list<string> SQL ifadeleri */
    public function toSql(string $driver): array
    {
        $defs = [];
        foreach ($this->columns as $col) {
            $defs[] = $this->columnSql($col, $driver);
        }
        foreach ($this->foreigns as $fk) {
            $defs[] = sprintf(
                'FOREIGN KEY (`%s`) REFERENCES `%s`(`id`) ON DELETE %s',
                $fk['column'],
                $fk['table'],
                $fk['on_delete'],
            );
        }
        $sql = sprintf("CREATE TABLE `%s` (\n  %s\n)", $this->table, implode(",\n  ", $defs));
        if ($driver === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        $statements = [$sql];
        foreach ($this->indexes as $ix) {
            $statements[] = sprintf(
                'CREATE %sINDEX `%s` ON `%s` (%s)',
                $ix['type'] === 'unique' ? 'UNIQUE ' : '',
                $ix['name'],
                $this->table,
                implode(', ', array_map(fn ($c) => "`{$c}`", $ix['columns'])),
            );
        }
        return $statements;
    }

    /** @return list<string> ALTER TABLE ADD COLUMN ifadeleri */
    public function toAlterSql(string $driver): array
    {
        $statements = [];
        foreach ($this->columns as $col) {
            $statements[] = sprintf('ALTER TABLE `%s` ADD COLUMN %s', $this->table, $this->columnSql($col, $driver));
        }
        foreach ($this->indexes as $ix) {
            $statements[] = sprintf(
                'CREATE %sINDEX `%s` ON `%s` (%s)',
                $ix['type'] === 'unique' ? 'UNIQUE ' : '',
                $ix['name'],
                $this->table,
                implode(', ', array_map(fn ($c) => "`{$c}`", $ix['columns'])),
            );
        }
        return $statements;
    }

    /** @param array<string, mixed> $col */
    private function columnSql(array $col, string $driver): string
    {
        $sqlite = $driver === 'sqlite';
        $type = match ($col['type']) {
            'id' => $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'integer' => $sqlite ? 'INTEGER' : 'INT',
            'bigint' => $sqlite ? 'INTEGER' : 'BIGINT',
            'float' => $sqlite ? 'REAL' : 'DOUBLE',
            'string' => 'VARCHAR(' . ($col['length'] ?? 255) . ')',
            'text' => $sqlite ? 'TEXT' : 'LONGTEXT',
            'boolean' => $sqlite ? 'INTEGER' : 'TINYINT(1)',
            'date' => $sqlite ? 'TEXT' : 'DATE',
            'datetime' => $sqlite ? 'TEXT' : 'DATETIME',
            default => 'TEXT',
        };
        $sql = "`{$col['name']}` {$type}";
        if ($col['type'] === 'id') {
            return $sql;
        }
        $sql .= $col['nullable'] ? ' NULL' : ' NOT NULL';
        if ($col['has_default']) {
            $default = $col['default'];
            $sql .= ' DEFAULT ' . match (true) {
                $default === null => 'NULL',
                is_bool($default) => (string) (int) $default,
                is_int($default), is_float($default) => (string) $default,
                default => "'" . str_replace("'", "''", (string) $default) . "'",
            };
        }
        if ($col['unique']) {
            $sql .= ' UNIQUE';
        }
        return $sql;
    }
}
