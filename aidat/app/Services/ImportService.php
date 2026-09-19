<?php

declare(strict_types=1);

namespace Aidat\Services;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Core\Exceptions\DomainException;
use Aidat\Core\Money;

/**
 * Banka ekstresi (CSV) içe aktarma ve eşleştirme kuyruğu.
 * Sütunlar başlıktan tahmin edilir (tarih / tutar / açıklama); kapı no ve ad soyad üzerinden bölüm önerisi yapılır.
 */
final class ImportService
{
    private Database $db;

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    /** @return array{import_id: int, rows: int, suggested: int} */
    public function importCsv(int $buildingId, int $accountId, string $tmpPath, string $fileName): array
    {
        $raw = file_get_contents($tmpPath);
        if ($raw === false || trim($raw) === '') {
            throw new DomainException('Dosya boş veya okunamadı.');
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1254');
        }
        $raw = ltrim($raw, "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $lines = array_values(array_filter($lines, static fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            throw new DomainException('Dosyada veri satırı yok.');
        }
        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : (substr_count($lines[0], "\t") > substr_count($lines[0], ',') ? "\t" : ',');
        $header = array_map(fn ($h) => mb_strtolower(trim($h, " \"'")), str_getcsv($lines[0], $delimiter, '"', '\\'));
        $find = static function (array $needles) use ($header): ?int {
            foreach ($header as $i => $h) {
                foreach ($needles as $n) {
                    if (str_contains($h, $n)) {
                        return $i;
                    }
                }
            }
            return null;
        };
        $iDate = $find(['tarih', 'date', 'islem tarihi', 'i̇şlem tarihi']);
        $iAmount = $find(['tutar', 'amount', 'miktar', 'işlem tutarı', 'islem tutari']);
        $iDesc = $find(['açıklama', 'aciklama', 'description', 'detay', 'işlem açıklaması']);
        $iRef = $find(['referans', 'dekont', 'fiş', 'fis', 'reference']);
        $iName = $find(['gönderen', 'gonderen', 'karşı', 'karsi', 'ad soyad', 'unvan', 'counterparty']);
        if ($iDate === null || $iAmount === null) {
            throw new DomainException('Başlık satırında "Tarih" ve "Tutar" sütunları bulunamadı. Beklenen sütunlar: Tarih; Tutar; Açıklama (isteğe bağlı: Referans, Gönderen).');
        }
        $units = $this->db->fetchAll('SELECT u.id, u.door_no, b.name AS block_name FROM units u LEFT JOIN blocks b ON b.id = u.block_id WHERE u.building_id = ?', [$buildingId]);
        $people = $this->db->fetchAll(
            'SELECT DISTINCT o.unit_id, p.first_name, p.last_name, p.company_name FROM occupancies o JOIN people p ON p.id = o.person_id WHERE o.building_id = ? AND (o.end_date IS NULL OR o.end_date >= ?)',
            [$buildingId, Dates::today()],
        );
        return $this->db->transaction(function () use ($buildingId, $accountId, $fileName, $lines, $delimiter, $iDate, $iAmount, $iDesc, $iRef, $iName, $units, $people): array {
            $importId = $this->db->insert('bank_imports', ['building_id' => $buildingId, 'account_id' => $accountId, 'file_name' => mb_substr($fileName, 0, 190), 'row_count' => 0, 'matched_count' => 0, 'status' => 'bekliyor', 'created_by' => $this->app->auth()->id(), 'created_at' => Database::now()]);
            $n = 0;
            $suggested = 0;
            foreach (array_slice($lines, 1) as $idx => $line) {
                $cols = str_getcsv($line, $delimiter, '"', '\\');
                $date = Dates::parse(trim((string) ($cols[$iDate] ?? '')));
                $amountRaw = trim((string) ($cols[$iAmount] ?? ''));
                if ($date === null || $amountRaw === '') {
                    continue;
                }
                try {
                    $amount = Money::parse($amountRaw);
                } catch (\InvalidArgumentException) {
                    continue;
                }
                if ($amount <= 0) {
                    continue; // yalnızca gelen tutarlar
                }
                $desc = $iDesc !== null ? trim((string) ($cols[$iDesc] ?? '')) : '';
                $name = $iName !== null ? trim((string) ($cols[$iName] ?? '')) : '';
                $ref = $iRef !== null ? trim((string) ($cols[$iRef] ?? '')) : null;
                $unitId = $this->suggestUnit($desc . ' ' . $name, $units, $people);
                if ($unitId !== null) {
                    $suggested++;
                }
                $this->db->insert('bank_import_rows', [
                    'import_id' => $importId, 'row_no' => $idx + 1, 'txn_date' => $date, 'amount' => $amount, 'description' => $desc,
                    'reference_no' => $ref ?: null, 'counterparty' => $name ?: null, 'suggested_unit_id' => $unitId, 'status' => 'bekliyor', 'created_at' => Database::now(),
                ]);
                $n++;
            }
            $this->db->update('bank_imports', ['row_count' => $n], 'id = ?', [$importId]);
            $this->app->audit()->log('import.create', 'bank_import', $importId, null, ['rows' => $n, 'file' => $fileName], $buildingId, 'Banka ekstresi içe aktarıldı: ' . $n . ' satır');
            return ['import_id' => $importId, 'rows' => $n, 'suggested' => $suggested];
        });
    }

    /** @param list<array<string, mixed>> $units @param list<array<string, mixed>> $people */
    private function suggestUnit(string $text, array $units, array $people): ?int
    {
        $t = mb_strtolower($text);
        // Ad soyad eşleşmesi önce (daha güvenilir)
        foreach ($people as $p) {
            $full = mb_strtolower(trim(($p['company_name'] ?: ($p['first_name'] . ' ' . $p['last_name']))));
            if ($full !== '' && mb_strlen($full) > 4 && str_contains($t, $full)) {
                return (int) $p['unit_id'];
            }
        }
        // Kapı no kalıpları: "daire 5", "d:5", "no 5", "no:5", "d-5", "5 nolu"
        if (preg_match('/(?:daire|d\.?|no\.?|kapı|kapi|bölüm|bolum)\s*[:\-]?\s*([0-9]{1,4}[a-z]?)\b/u', $t, $m) || preg_match('/\b([0-9]{1,4})\s*(?:nolu|no\'lu|numaralı)/u', $t, $m)) {
            $door = $m[1];
            $matches = array_values(array_filter($units, static fn ($u) => mb_strtolower((string) $u['door_no']) === $door));
            if (count($matches) === 1) {
                return (int) $matches[0]['id'];
            }
            // Blok belirtilmişse
            foreach ($matches as $u) {
                if ($u['block_name'] && str_contains($t, mb_strtolower((string) $u['block_name']))) {
                    return (int) $u['id'];
                }
            }
        }
        return null;
    }

    public function match(int $rowId, int $buildingId, int $unitId, string $method = 'havale'): int
    {
        $row = $this->db->fetch('SELECT r.*, i.account_id, i.building_id FROM bank_import_rows r JOIN bank_imports i ON i.id = r.import_id WHERE r.id = ? AND i.building_id = ?', [$rowId, $buildingId]) ?? throw new DomainException('Satır bulunamadı.');
        if ($row['status'] !== 'bekliyor') {
            throw new DomainException('Bu satır zaten işlenmiş.');
        }
        $paymentId = (new PaymentService($this->app))->create($buildingId, [
            'unit_id' => $unitId,
            'account_id' => (int) $row['account_id'],
            'payment_date' => $row['txn_date'],
            'amount' => (int) $row['amount'],
            'method' => $method,
            'reference_no' => $row['reference_no'],
            'description' => 'Banka ekstresi: ' . mb_substr((string) $row['description'], 0, 150),
            'allocation_mode' => 'eski',
            'import_row_id' => $rowId,
        ]);
        $this->db->update('bank_import_rows', ['matched_unit_id' => $unitId, 'payment_id' => $paymentId, 'status' => 'eslesti'], 'id = ?', [$rowId]);
        $this->refresh((int) $row['import_id']);
        return $paymentId;
    }

    public function ignore(int $rowId, int $buildingId): void
    {
        $row = $this->db->fetch('SELECT r.* FROM bank_import_rows r JOIN bank_imports i ON i.id = r.import_id WHERE r.id = ? AND i.building_id = ?', [$rowId, $buildingId]) ?? throw new DomainException('Satır bulunamadı.');
        $this->db->update('bank_import_rows', ['status' => 'yoksayildi'], 'id = ?', [$rowId]);
        $this->refresh((int) $row['import_id']);
    }

    private function refresh(int $importId): void
    {
        $matched = $this->db->fetchInt("SELECT COUNT(*) FROM bank_import_rows WHERE import_id = ? AND status = 'eslesti'", [$importId]);
        $pending = $this->db->fetchInt("SELECT COUNT(*) FROM bank_import_rows WHERE import_id = ? AND status = 'bekliyor'", [$importId]);
        $this->db->update('bank_imports', ['matched_count' => $matched, 'status' => $pending === 0 ? 'tamamlandi' : 'bekliyor'], 'id = ?', [$importId]);
    }
}
