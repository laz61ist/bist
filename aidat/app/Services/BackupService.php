<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Exceptions\DomainException;
use PDO;

/** Yedek: SQLite için VACUUM INTO; MySQL için PHP ile SQL dökümü. */
final class BackupService
{
    public function __construct(private readonly Application $app)
    {
    }

    public function create(): string
    {
        $dir = $this->app->basePath . '/storage/backups';
        if (!is_dir($dir) && !mkdir($dir, 0o770, true) && !is_dir($dir)) {
            throw new DomainException('Yedek dizini oluşturulamadı.');
        }
        $db = $this->app->db();
        $stamp = date('Ymd-His');
        if ($db->isSqlite()) {
            $target = $dir . '/aidat-' . $stamp . '.sqlite';
            $db->pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $target) . "'");
            return $target;
        }
        $target = $dir . '/aidat-' . $stamp . '.sql';
        $fh = fopen($target, 'wb');
        if ($fh === false) {
            throw new DomainException('Yedek dosyası yazılamadı.');
        }
        fwrite($fh, "-- Aidat Yönetim yedeği " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n");
        $tables = array_column($db->fetchAll('SHOW TABLES'), 0) ?: array_map(static fn ($r) => array_values($r)[0], $db->fetchAll('SHOW TABLES'));
        foreach ($tables as $table) {
            $create = $db->fetch("SHOW CREATE TABLE `{$table}`");
            $ddl = $create['Create Table'] ?? array_values($create)[1];
            fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n");
            $stmt = $db->pdo()->query("SELECT * FROM `{$table}`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $vals = array_map(static fn ($v) => $v === null ? 'NULL' : $db->pdo()->quote((string) $v), $row);
                fwrite($fh, "INSERT INTO `{$table}` (`" . implode('`,`', array_keys($row)) . "`) VALUES (" . implode(',', $vals) . ");\n");
            }
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        return $target;
    }

    /** @return list<array{name: string, size: int, time: int, path: string}> */
    public function list(): array
    {
        $dir = $this->app->basePath . '/storage/backups';
        $out = [];
        foreach (glob($dir . '/aidat-*') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f), 'path' => $f];
        }
        usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }
}
