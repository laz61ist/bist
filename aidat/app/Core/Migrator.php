<?php

declare(strict_types=1);

namespace Aidat\Core;

use RuntimeException;

/**
 * database/migrations/YYYY_MM_DD_NNN_aciklama.php dosyalarını sırayla uygular.
 * Her dosya `up(Schema $schema)` ve `down(Schema $schema)` metodlu anonim sınıf döndürür.
 */
final class Migrator
{
    public function __construct(private readonly Database $db, private readonly string $dir)
    {
    }

    private function ensureTable(): void
    {
        if (!$this->db->tableExists('migrations')) {
            (new Schema($this->db))->create('migrations', function (Blueprint $t): void {
                $t->id();
                $t->string('name', 190)->unique();
                $t->integer('batch');
                $t->datetime('ran_at');
            });
        }
    }

    /** @return list<string> */
    public function pending(): array
    {
        $this->ensureTable();
        $done = array_map('strval', $this->db->fetchAll('SELECT name FROM migrations') ? array_column($this->db->fetchAll('SELECT name FROM migrations'), 'name') : []);
        $files = glob($this->dir . '/*.php') ?: [];
        sort($files);
        $pending = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $done, true)) {
                $pending[] = $name;
            }
        }
        return $pending;
    }

    /** @return list<string> uygulanan migrasyonlar */
    public function migrate(): array
    {
        $pending = $this->pending();
        if ($pending === []) {
            return [];
        }
        $batch = $this->db->fetchInt('SELECT COALESCE(MAX(batch), 0) FROM migrations') + 1;
        $schema = new Schema($this->db);
        $ran = [];
        foreach ($pending as $name) {
            $migration = require $this->dir . '/' . $name . '.php';
            if (!is_object($migration) || !method_exists($migration, 'up')) {
                throw new RuntimeException("Geçersiz migrasyon: {$name}");
            }
            $this->db->transaction(function () use ($migration, $schema, $name, $batch): void {
                $migration->up($schema);
                $this->db->insert('migrations', ['name' => $name, 'batch' => $batch, 'ran_at' => Database::now()]);
            });
            $ran[] = $name;
        }
        return $ran;
    }

    /** Son batch'i geri alır. @return list<string> */
    public function rollback(): array
    {
        $this->ensureTable();
        $batch = $this->db->fetchInt('SELECT COALESCE(MAX(batch), 0) FROM migrations');
        if ($batch === 0) {
            return [];
        }
        $rows = $this->db->fetchAll('SELECT name FROM migrations WHERE batch = ? ORDER BY id DESC', [$batch]);
        $schema = new Schema($this->db);
        $rolled = [];
        foreach ($rows as $row) {
            $migration = require $this->dir . '/' . $row['name'] . '.php';
            $this->db->transaction(function () use ($migration, $schema, $row): void {
                if (method_exists($migration, 'down')) {
                    $migration->down($schema);
                }
                $this->db->delete('migrations', 'name = ?', [$row['name']]);
            });
            $rolled[] = $row['name'];
        }
        return $rolled;
    }
}
