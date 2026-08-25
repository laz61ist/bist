<?php

declare(strict_types=1);

namespace Bist\Data;

use Bist\Domain\Kriter;
use Bist\Domain\KriterSeti;
use Bist\Domain\Operator;
use JsonException;
use PDO;
use RuntimeException;

/**
 * Kriter setlerini sqlite'ta saklar.
 *
 * Neden gerekce alani var: kullanicinin esigi NEDEN koydugunu kendi
 * cumlesiyle yazmasi, ciktiya sahip cikmasini saglar. Esik sistemin
 * degil kullanicinin olur (G-19).
 *
 * Tum sorgular hazirlanmis ifadedir; kullanici metni SQL'e girmez.
 */
final class KriterDeposu
{
    private PDO $pdo;

    public function __construct(private readonly string $dosya)
    {
        $this->pdo = new PDO('sqlite:' . $dosya, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON');

        // D3 (#7): WAL, tek instance icinde bile es zamanli istekte
        // "database is locked" hatalarini azaltir. :memory: WAL
        // desteklemez, orada sessizce atlanir.
        if ($dosya !== ':memory:') {
            $this->pdo->exec('PRAGMA journal_mode = WAL');
        }

        // Kilit bekleme suresi; olmadan es zamanli yazma aninda patlar.
        $this->pdo->exec('PRAGMA busy_timeout = 5000');

        $this->semaKur();
    }

    /** Bir PRAGMA degerini okur. Dagitim dogrulamasi ve test icin. */
    public function pragma(string $ad): string
    {
        if (!preg_match('/\A[a-z_]+\z/', $ad)) {
            throw new RuntimeException("Geçersiz PRAGMA adı: {$ad}");
        }

        return (string) $this->pdo->query("PRAGMA {$ad}")?->fetchColumn();
    }

    /**
     * Veritabaninin tutarli bir kopyasini alir.
     *
     * VACUUM INTO, dosyayi kopyalamaktan farkli olarak acik bir yazma
     * islemi sirasinda da tutarli sonuc verir — WAL modunda dosya
     * kopyalamak bozuk yedek uretebilir.
     */
    public function yedekle(string $hedef): void
    {
        $dizin = dirname($hedef);
        if (!is_dir($dizin)) {
            // @ ile bastirilan uyari, hemen ardindan istisnaya tasiniyor;
            // tani kaybolmuyor, yalnizca cift raporlama onleniyor.
            if (!@mkdir($dizin, 0o770, true) && !is_dir($dizin)) {
                $neden = error_get_last()['message'] ?? 'bilinmeyen neden';
                throw new RuntimeException("Yedek dizini oluşturulamadı ({$dizin}): {$neden}");
            }
        }

        try {
            $st = $this->pdo->prepare('VACUUM INTO :hedef');
            $st->execute([':hedef' => $hedef]);
        } catch (\Throwable $e) {
            throw new RuntimeException("Yedek alınamadı: {$hedef}", previous: $e);
        }
    }

    private function semaKur(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS kriter_seti (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                ad         TEXT NOT NULL,
                gerekce    TEXT NOT NULL DEFAULT '',
                kriterler  TEXT NOT NULL,
                olusturma  TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL);
    }

    /**
     * Baglantinin gercekten calistigini dogrular.
     *
     * D5 (#9): /saglik onceden hicbir seye dokunmuyordu. Bu sorgu ucuzdur
     * ama gercektir: dosya acilabiliyor ve okunabiliyor mu.
     */
    public function calisiyorMu(): bool
    {
        try {
            return $this->pdo->query('SELECT 1')?->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function kaydet(string $ad, KriterSeti $seti, string $gerekce = ''): int
    {
        $kriterler = array_map(static fn (Kriter $k): array => [
            'ad' => $k->ad,
            'anahtar' => $k->metrikAnahtari,
            'operator' => $k->operator->value,
            'esik' => $k->esik,
        ], $seti->kriterler);

        $st = $this->pdo->prepare(
            'INSERT INTO kriter_seti (ad, gerekce, kriterler) VALUES (:ad, :gerekce, :kriterler)',
        );
        $st->execute([
            ':ad' => $ad,
            ':gerekce' => $gerekce,
            ':kriterler' => json_encode($kriterler, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getir(int $id): ?KriterKaydi
    {
        $st = $this->pdo->prepare('SELECT * FROM kriter_seti WHERE id = :id');
        $st->execute([':id' => $id]);
        $satir = $st->fetch();

        return $satir === false ? null : $this->kayda($satir);
    }

    /** @return list<KriterKaydi> */
    public function hepsi(): array
    {
        $st = $this->pdo->query('SELECT * FROM kriter_seti ORDER BY id DESC');

        return array_map($this->kayda(...), $st === false ? [] : $st->fetchAll());
    }

    /** @param array<string, mixed> $satir */
    private function kayda(array $satir): KriterKaydi
    {
        try {
            $ham = json_decode((string) $satir['kriterler'], true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Kriter seti #{$satir['id']} okunamadı.", previous: $e);
        }

        $kriterler = array_map(static fn (array $k): Kriter => new Kriter(
            (string) $k['ad'],
            (string) $k['anahtar'],
            Operator::from((string) $k['operator']),
            (float) $k['esik'],
        ), is_array($ham) ? $ham : []);

        return new KriterKaydi(
            id: (int) $satir['id'],
            ad: (string) $satir['ad'],
            seti: new KriterSeti($kriterler),
            gerekce: (string) $satir['gerekce'],
            olusturma: (string) $satir['olusturma'],
        );
    }
}
