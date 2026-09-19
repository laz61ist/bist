<?php

declare(strict_types=1);

namespace Aidat\Database\Seeds;

use Aidat\Core\Application;
use Aidat\Core\Database;
use Aidat\Core\Dates;
use Aidat\Services\BudgetService;
use Aidat\Services\ChargeService;
use Aidat\Services\ExpenseService;
use Aidat\Services\IncomeService;
use Aidat\Services\LateFeeService;
use Aidat\Services\LedgerService;
use Aidat\Services\MeterService;
use Aidat\Services\NotificationService;
use Aidat\Services\PaymentService;
use Aidat\Services\PeriodService;
use Aidat\Services\TransferService;

/**
 * Demo verisi (kurgusal; gerçek kişi/firma adı içermez). Şifre tüm hesaplarda: Demo1234!
 * Yeniden çalıştırıldığında mevcut demo yapısı varsa dokunmaz.
 */
final class DemoSeeder
{
    private Database $db;
    private const PASSWORD = 'Demo1234!';

    public function __construct(private readonly Application $app)
    {
        $this->db = $app->db();
    }

    public function run(): string
    {
        if ($this->db->fetch("SELECT id FROM buildings WHERE name LIKE 'DEMO %'") !== null) {
            return 'Demo verisi zaten var; atlandı.';
        }
        mt_srand(20260919);
        $now = Database::now();
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);

        // --- Kullanıcılar
        $users = [];
        foreach ([
            ['admin', 'Demo Süper Yönetici', 'demo.admin@aidat.local', 'admin'],
            ['manager', 'Demo Yönetici', 'demo.yonetici@aidat.local', 'manager'],
            ['accountant', 'Demo Muhasebe', 'demo.muhasebe@aidat.local', 'accountant'],
            ['auditor', 'Demo Denetçi', 'demo.denetci@aidat.local', 'auditor'],
            ['staff', 'Demo Görevli', 'demo.gorevli@aidat.local', 'staff'],
            ['owner', 'Ayşe Demir', 'demo.malik@aidat.local', 'owner'],
            ['tenant', 'Mehmet Kaya', 'demo.kiraci@aidat.local', 'tenant'],
        ] as [$key, $name, $email, $role]) {
            $users[$key] = $this->db->insert('users', ['name' => $name, 'email' => $email, 'phone' => '05' . mt_rand(300000000, 559999999), 'password_hash' => $hash, 'role' => $role, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- Yapı
        $b = $this->db->insert('buildings', ['name' => 'DEMO Çınar Sitesi', 'type' => 'site', 'address' => 'Çınar Mah. Defne Sok. No: 12', 'city' => 'İstanbul', 'district' => 'Kadıköy', 'management_start' => '2024-01-01', 'currency' => 'TRY', 'iban' => 'TR330006100519786457841326', 'bank_name' => 'Demo Bank', 'account_holder' => 'DEMO Çınar Sitesi Yönetimi', 'phone' => '0216 000 00 00', 'email' => 'yonetim@cinar.local', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        foreach (['manager', 'accountant', 'auditor', 'staff'] as $key) {
            $this->db->insert('building_users', ['user_id' => $users[$key], 'building_id' => $b, 'role' => $key, 'is_default' => 1, 'created_at' => $now]);
        }
        $this->db->insert('building_users', ['user_id' => $users['owner'], 'building_id' => $b, 'role' => 'owner', 'created_at' => $now]);
        $this->db->insert('building_users', ['user_id' => $users['tenant'], 'building_id' => $b, 'role' => 'tenant', 'created_at' => $now]);
        $this->app->settings()->setMany($b, ['due_day' => '10', 'late_fee_rate' => '5', 'debt_visibility' => 'kapi_no', 'receipt_prefix' => 'CNR']);

        // --- Hesaplar
        $kasa = $this->db->insert('accounts', ['building_id' => $b, 'name' => 'Nakit kasa', 'type' => 'kasa', 'opening_balance' => 250000, 'opening_date' => '2026-01-01', 'is_default' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $banka = $this->db->insert('accounts', ['building_id' => $b, 'name' => 'Demo Bank vadesiz', 'type' => 'banka', 'bank_name' => 'Demo Bank', 'iban' => 'TR330006100519786457841326', 'opening_balance' => 4800000, 'opening_date' => '2026-01-01', 'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        // --- Bloklar, gruplar, bölümler
        $blockA = $this->db->insert('blocks', ['building_id' => $b, 'name' => 'A Blok', 'code' => 'A', 'floors' => 4, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $blockB = $this->db->insert('blocks', ['building_id' => $b, 'name' => 'B Blok', 'code' => 'B', 'floors' => 4, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now]);
        $gDaire = $this->db->insert('fee_groups', ['building_id' => $b, 'name' => 'Daire', 'created_at' => $now, 'updated_at' => $now]);
        $gDukkan = $this->db->insert('fee_groups', ['building_id' => $b, 'name' => 'Dükkan', 'created_at' => $now, 'updated_at' => $now]);
        $units = [];
        foreach ([[$blockA, 'A'], [$blockB, 'B']] as [$blockId, $code]) {
            for ($i = 1; $i <= 12; $i++) {
                $isShop = $i === 1;
                $units[] = $this->db->insert('units', [
                    'building_id' => $b, 'block_id' => $blockId, 'fee_group_id' => $isShop ? $gDukkan : $gDaire,
                    'door_no' => (string) $i, 'floor' => (string) intdiv($i - 1, 3), 'type' => $isShop ? 'dukkan' : 'daire',
                    'gross_m2' => $isShop ? 140 : (in_array($i % 3, [1], true) ? 95 : 120), 'net_m2' => $isShop ? 120 : 100, 'land_share' => $isShop ? 14 : 10,
                    'status' => 'dolu', 'liability_mode' => 'malik', 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        // Bir bölüm boş
        $this->db->update('units', ['status' => 'bos'], 'id = ?', [$units[17]]);

        // --- Kişiler ve oturumlar (kurgusal adlar)
        $first = ['Ali', 'Zeynep', 'Hasan', 'Elif', 'Murat', 'Fatma', 'Emre', 'Selin', 'Burak', 'Derya', 'Kerem', 'Nazlı', 'Okan', 'Pınar', 'Serkan', 'Tuğba', 'Volkan', 'Yasemin', 'Cem', 'Gizem', 'Halil', 'İrem', 'Kaan', 'Leyla', 'Onur', 'Melis'];
        $last = ['Yılmaz', 'Şahin', 'Çelik', 'Öztürk', 'Aydın', 'Arslan', 'Doğan', 'Kılıç', 'Aslan', 'Çetin', 'Kara', 'Koç', 'Kurt', 'Özkan', 'Şimşek', 'Polat', 'Yıldız', 'Erdoğan', 'Güneş', 'Bulut', 'Acar', 'Taş', 'Korkmaz', 'Uçar', 'Aksoy', 'Ateş'];
        $people = [];
        foreach ($units as $idx => $uid) {
            $isOwnerDemo = $idx === 3;   // A-4 → demo.malik
            $isTenantDemo = $idx === 6;  // A-7 → demo.kiraci oturuyor, maliki başka
            $ownerName = $isOwnerDemo ? ['Ayşe', 'Demir'] : [$first[$idx % 26], $last[($idx * 7) % 26]];
            $pid = $this->db->insert('people', ['building_id' => $b, 'type' => 'gercek', 'first_name' => $ownerName[0], 'last_name' => $ownerName[1], 'phone' => '05' . mt_rand(300000000, 559999999), 'email' => $isOwnerDemo ? 'demo.malik@aidat.local' : strtolower(\Aidat\Core\Str::slug($ownerName[0] . '.' . $ownerName[1])) . '@ornek.local', 'contact_consent' => 1, 'user_id' => $isOwnerDemo ? $users['owner'] : null, 'created_at' => $now, 'updated_at' => $now]);
            $people[$uid]['owner'] = $pid;
            $this->db->insert('occupancies', ['building_id' => $b, 'unit_id' => $uid, 'person_id' => $pid, 'role' => 'malik', 'liability' => 'malik', 'is_notify_contact' => 1, 'start_date' => '2024-01-01', 'created_at' => $now, 'updated_at' => $now]);
            if ($isTenantDemo || in_array($idx, [9, 14, 20], true)) {
                $tName = $isTenantDemo ? ['Mehmet', 'Kaya'] : [$first[($idx + 5) % 26], $last[($idx * 3 + 1) % 26]];
                $tid = $this->db->insert('people', ['building_id' => $b, 'type' => 'gercek', 'first_name' => $tName[0], 'last_name' => $tName[1], 'phone' => '05' . mt_rand(300000000, 559999999), 'email' => $isTenantDemo ? 'demo.kiraci@aidat.local' : null, 'contact_consent' => 1, 'user_id' => $isTenantDemo ? $users['tenant'] : null, 'created_at' => $now, 'updated_at' => $now]);
                $this->db->insert('occupancies', ['building_id' => $b, 'unit_id' => $uid, 'person_id' => $tid, 'role' => 'kiraci', 'liability' => 'kiraci', 'is_notify_contact' => 1, 'start_date' => '2025-06-01', 'created_at' => $now, 'updated_at' => $now]);
                $this->db->update('units', ['liability_mode' => 'kiraci'], 'id = ?', [$uid]);
                $people[$uid]['tenant'] = $tid;
            }
        }
        $this->db->update('occupancies', ['end_date' => '2026-05-31'], 'unit_id = ?', [$units[17]]);
        $this->db->insert('unit_vehicles', ['unit_id' => $units[3], 'plate' => '34ABC123', 'description' => 'Beyaz sedan', 'created_at' => $now]);

        // --- Tüzel kişi (dükkan sahibi şirket)
        $company = $this->db->insert('people', ['building_id' => $b, 'type' => 'tuzel', 'first_name' => 'Yetkili', 'last_name' => 'Kişi', 'company_name' => 'DEMO Market Ltd. Şti.', 'phone' => '02160000001', 'email' => 'market@ornek.local', 'identity_no' => '1234567890', 'created_at' => $now, 'updated_at' => $now]);
        $this->db->update('occupancies', ['end_date' => '2025-12-31'], 'unit_id = ? AND role = ?', [$units[12], 'malik']);
        $this->db->insert('occupancies', ['building_id' => $b, 'unit_id' => $units[12], 'person_id' => $company, 'role' => 'malik', 'liability' => 'malik', 'is_notify_contact' => 1, 'start_date' => '2026-01-01', 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('handovers', ['building_id' => $b, 'unit_id' => $units[12], 'from_person_id' => $people[$units[12]]['owner'], 'to_person_id' => $company, 'handover_date' => '2026-01-01', 'balance_mode' => 'devreder', 'transferred_balance' => 0, 'deposit_transferred' => 0, 'notes' => 'Dükkan satışı ile devir.', 'created_by' => $users['manager'], 'created_at' => $now]);

        // --- Kategoriler, tedarikçiler, sözleşmeler
        (new ExpenseService($this->app))->ensureDefaultCategories($b);
        $cat = fn (string $name): ?int => ($id = $this->db->fetchColumn('SELECT id FROM expense_categories WHERE building_id = ? AND name = ?', [$b, $name])) ? (int) $id : null;
        $incCat = fn (string $name): ?int => ($id = $this->db->fetchColumn('SELECT id FROM income_categories WHERE building_id = ? AND name = ?', [$b, $name])) ? (int) $id : null;
        $vendors = [];
        foreach ([['DEMO Asansör Bakım A.Ş.', 'Asansör bakımı'], ['DEMO Temizlik Hizmetleri', 'Temizlik'], ['DEMO Elektrik Dağıtım', 'Elektrik'], ['DEMO Su İdaresi', 'Su'], ['DEMO Sigorta Acentesi', 'Sigorta'], ['DEMO Bahçe Peyzaj', 'Bahçe']] as [$vn, $vs]) {
            $vendors[$vs] = $this->db->insert('vendors', ['building_id' => $b, 'name' => $vn, 'service_type' => $vs, 'phone' => '0216 111 11 11', 'email' => 'info@ornek.local', 'tax_no' => (string) mt_rand(1000000000, 9999999999), 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->db->insert('contracts', ['building_id' => $b, 'vendor_id' => $vendors['Asansör bakımı'], 'title' => 'Asansör aylık bakım sözleşmesi', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'amount' => 450000, 'billing_period' => 'aylik', 'renewal_notice_days' => 45, 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('contracts', ['building_id' => $b, 'vendor_id' => $vendors['Temizlik'], 'title' => 'Ortak alan temizlik sözleşmesi', 'start_date' => '2025-10-01', 'end_date' => '2026-10-15', 'amount' => 950000, 'billing_period' => 'aylik', 'renewal_notice_days' => 30, 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now]);

        // --- Personel, demirbaş
        $this->db->insert('staff', ['building_id' => $b, 'full_name' => 'Recep Kapıcı', 'position' => 'kapici', 'phone' => '05' . mt_rand(300000000, 559999999), 'start_date' => '2022-03-01', 'salary' => 2200000, 'sgk_no' => '1234567890123', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('assets', ['building_id' => $b, 'name' => 'Hidrofor pompası', 'category' => 'Tesisat', 'purchase_date' => '2025-04-12', 'cost' => 2850000, 'location' => 'Kazan dairesi', 'status' => 'aktif', 'warranty_until' => '2027-04-12', 'vendor_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('assets', ['building_id' => $b, 'name' => 'Güvenlik kamera sistemi (8 kanal)', 'category' => 'Güvenlik', 'purchase_date' => '2024-09-01', 'cost' => 4200000, 'location' => 'Giriş / otopark', 'status' => 'aktif', 'warranty_until' => '2026-09-01', 'created_at' => $now, 'updated_at' => $now]);

        // --- Bu noktadan sonra servisler (yönetici olarak)
        $this->app->session()->start();
        $this->app->session()->set('auth_user_id', $users['manager']);
        $this->app->auth()->forgetUser();
        $this->app->session()->set('building_id', $b);

        $charges = new ChargeService($this->app);
        $payments = new PaymentService($this->app);
        $expenses = new ExpenseService($this->app);
        $incomes = new IncomeService($this->app);

        // --- Aidat planı: aylık, gruba göre (daire 2.500 ₺, dükkan 4.000 ₺), Ocak 2026'dan itibaren
        $planUnits = $charges->planUnits($b);
        $planData = ['name' => 'Aylık aidat 2026', 'charge_type' => 'aidat', 'block_id' => null, 'fee_group_id' => null, 'period' => '2026-01', 'due_date' => '2026-01-10', 'recurrence' => 'aylik', 'repeat_until' => '2026-12', 'distribution' => 'grup', 'total_amount' => 0, 'unit_amount' => 0, 'group_amounts' => [$gDaire => 250000, $gDukkan => 400000], 'liability' => 'bolum', 'vat_mode' => 'yok', 'vat_rate' => 0, 'description' => 'Genel kurul kararı 2025/3 uyarınca aylık aidat.'];
        $lines = $charges->distribute($planData, $planUnits);
        $planId = $charges->savePlan($b, $planData, $lines);
        $charges->approvePlan($planId, $b);
        $currentPeriod = Dates::currentPeriod();
        foreach (Dates::periodRange('2026-01', $currentPeriod) as $p) {
            $charges->processPlan($planId, $b, $p);
        }
        // Demirbaş katkı payı (tek sefer, eşit)
        $dem = ['name' => 'Kamera sistemi yenileme katkı payı', 'charge_type' => 'demirbas', 'block_id' => null, 'fee_group_id' => null, 'period' => '2026-04', 'due_date' => '2026-04-30', 'recurrence' => 'tek', 'repeat_until' => null, 'distribution' => 'esit', 'total_amount' => 3600000, 'unit_amount' => 0, 'liability' => 'malik', 'vat_mode' => 'yok', 'vat_rate' => 0, 'description' => 'Genel kurul kararı 2026/1'];
        $demId = $charges->savePlan($b, $dem, $charges->distribute($dem, $planUnits));
        $charges->approvePlan($demId, $b);
        $charges->processPlan($demId, $b);
        // Taslak bir plan (yakıt)
        $fuel = ['name' => 'Kış dönemi yakıt payı', 'charge_type' => 'yakit', 'block_id' => null, 'fee_group_id' => null, 'period' => Dates::addMonths($currentPeriod, 1), 'due_date' => Dates::dayOfPeriod(Dates::addMonths($currentPeriod, 1), 15), 'recurrence' => 'tek', 'repeat_until' => null, 'distribution' => 'm2', 'total_amount' => 9600000, 'unit_amount' => 0, 'liability' => 'kiraci', 'vat_mode' => 'yok', 'vat_rate' => 0, 'description' => 'Doğalgaz faturası m² oranında dağıtım.'];
        $charges->savePlan($b, $fuel, $charges->distribute($fuel, $planUnits));

        // --- Tahsilatlar: çoğu bölüm zamanında öder; bazıları gecikmeli/eksik
        $profiles = [];
        foreach ($units as $idx => $uid) {
            $profiles[$uid] = match (true) {
                in_array($idx, [4, 11, 19], true) => 'debtor',   // hiç ödemedi (son 3 ay)
                in_array($idx, [7, 15], true) => 'partial',      // kısmi
                in_array($idx, [2, 22], true) => 'late',         // geç öder
                $idx === 17 => 'empty',
                default => 'regular',
            };
        }
        $periods = Dates::periodRange('2026-01', $currentPeriod);
        foreach ($units as $uid) {
            $profile = $profiles[$uid];
            foreach ($this->db->fetchAll("SELECT * FROM charges WHERE unit_id = ? AND charge_type = 'aidat' ORDER BY due_date", [$uid]) as $c) {
                $isRecent = $c['period'] >= Dates::addMonths($currentPeriod, -2);
                if ($profile === 'debtor' && $isRecent) {
                    continue;
                }
                if ($profile === 'empty' && $c['period'] >= '2026-06') {
                    continue;
                }
                $amount = (int) $c['amount'];
                if ($profile === 'partial' && $isRecent) {
                    $amount = intdiv($amount, 2);
                }
                $daysOffset = match ($profile) { 'late' => mt_rand(12, 40), 'partial' => mt_rand(0, 8), default => mt_rand(-6, 4) };
                $date = date('Y-m-d', strtotime($c['due_date'] . ' ' . ($daysOffset >= 0 ? '+' : '') . $daysOffset . ' days'));
                if ($date > Dates::today()) {
                    continue;
                }
                $method = mt_rand(0, 9) < 7 ? 'havale' : 'nakit';
                $payments->create($b, ['unit_id' => $uid, 'account_id' => $method === 'nakit' ? $kasa : $banka, 'payment_date' => $date, 'amount' => $amount, 'method' => $method, 'reference_no' => $method === 'havale' ? 'EFT' . mt_rand(100000, 999999) : null, 'description' => Dates::period($c['period']) . ' aidatı', 'allocation_mode' => 'eski']);
            }
            // Demirbaş payı: düzenli ve geç ödeyenler öder (FIFO nedeniyle tutar en eski açık borca gider)
            if (in_array($profile, ['regular', 'late'], true)) {
                $d = $this->db->fetch("SELECT * FROM charges WHERE unit_id = ? AND charge_type = 'demirbas'", [$uid]);
                if ($d) {
                    $payments->create($b, ['unit_id' => $uid, 'account_id' => $banka, 'payment_date' => '2026-05-0' . mt_rand(2, 9), 'amount' => (int) $d['amount'], 'method' => 'havale', 'reference_no' => 'EFT' . mt_rand(100000, 999999), 'description' => 'Demirbaş katkı payı', 'allocation_mode' => 'eski']);
                }
            }
        }
        // Bir avans (fazla ödeme)
        $payments->create($b, ['unit_id' => $units[0], 'account_id' => $banka, 'payment_date' => Dates::today(), 'amount' => 300000, 'method' => 'havale', 'reference_no' => 'EFT777001', 'description' => 'Gelecek ay için avans', 'allocation_mode' => 'avans']);
        // Bir iptal edilmiş tahsilat (mükerrer)
        $dupId = $payments->create($b, ['unit_id' => $units[1], 'account_id' => $kasa, 'payment_date' => Dates::today(), 'amount' => 250000, 'method' => 'nakit', 'description' => 'Mükerrer giriş', 'allocation_mode' => 'avans']);
        $payments->cancel($dupId, $b, 'Mükerrer kayıt; aynı tahsilat banka üzerinden girilmişti.');

        // --- Gecikme tazminatı uygula
        (new LateFeeService($this->app))->apply($b, Dates::today());

        // --- Giderler (aylık)
        foreach ($periods as $p) {
            $d = fn (int $day) => Dates::dayOfPeriod($p, $day);
            $isPast = $p < $currentPeriod;
            $expenses->create($b, ['category_id' => $cat('Kapıcı maaşı'), 'vendor_id' => null, 'account_id' => $banka, 'expense_date' => $d(1), 'period' => $p, 'amount' => 2200000, 'vat_mode' => 'yok', 'vat_rate' => 0, 'document_kind' => 'diger', 'document_no' => null, 'description' => 'Kapıcı maaşı · ' . Dates::period($p), 'status' => 'odendi']);
            $expenses->create($b, ['category_id' => $cat('SGK primi'), 'vendor_id' => null, 'account_id' => $banka, 'expense_date' => $d(26), 'period' => $p, 'amount' => 790000, 'vat_mode' => 'yok', 'vat_rate' => 0, 'document_kind' => 'diger', 'description' => 'SGK primi · ' . Dates::period($p), 'status' => $isPast ? 'odendi' : 'planlandi', 'due_date' => $d(26)]);
            $expenses->create($b, ['category_id' => $cat('Asansör bakımı'), 'vendor_id' => $vendors['Asansör bakımı'], 'account_id' => $banka, 'expense_date' => $d(5), 'period' => $p, 'amount' => 450000, 'vat_mode' => 'dahil', 'vat_rate' => 20, 'document_kind' => 'fatura', 'document_no' => 'ASN' . substr($p, 0, 4) . substr($p, 5) . '01', 'description' => 'Asansör aylık bakım', 'status' => 'odendi']);
            $expenses->create($b, ['category_id' => $cat('Temizlik görevlisi'), 'vendor_id' => $vendors['Temizlik'], 'account_id' => $banka, 'expense_date' => $d(3), 'period' => $p, 'amount' => 950000, 'vat_mode' => 'dahil', 'vat_rate' => 20, 'document_kind' => 'fatura', 'document_no' => 'TMZ' . mt_rand(1000, 9999), 'description' => 'Ortak alan temizlik hizmeti', 'status' => 'odendi']);
            $expenses->create($b, ['category_id' => $cat('Ortak alan elektriği'), 'vendor_id' => $vendors['Elektrik'], 'account_id' => $banka, 'expense_date' => $d(18), 'period' => $p, 'amount' => mt_rand(180000, 420000), 'vat_mode' => 'dahil', 'vat_rate' => 20, 'document_kind' => 'fatura', 'document_no' => 'ELK' . mt_rand(100000, 999999), 'description' => 'Ortak alan elektrik faturası', 'status' => $isPast ? 'odendi' : 'planlandi', 'due_date' => $d(28)]);
            $expenses->create($b, ['category_id' => $cat('Ortak alan suyu'), 'vendor_id' => $vendors['Su'], 'account_id' => $banka, 'expense_date' => $d(20), 'period' => $p, 'amount' => mt_rand(60000, 140000), 'vat_mode' => 'dahil', 'vat_rate' => 10, 'document_kind' => 'fatura', 'description' => 'Bahçe sulama ve ortak alan suyu', 'status' => 'odendi']);
            if (in_array(substr($p, 5), ['01', '04', '07'], true)) {
                $expenses->create($b, ['category_id' => $cat('Bahçe bakımı'), 'vendor_id' => $vendors['Bahçe'], 'account_id' => $kasa, 'expense_date' => $d(12), 'period' => $p, 'amount' => 650000, 'vat_mode' => 'haric', 'vat_rate' => 20, 'document_kind' => 'fatura', 'description' => 'Mevsimlik bahçe bakımı ve budama', 'status' => 'odendi']);
            }
        }
        $expenses->create($b, ['category_id' => $cat('Bina sigortası (DASK / yangın)'), 'vendor_id' => $vendors['Sigorta'], 'account_id' => $banka, 'expense_date' => '2026-02-14', 'period' => '2026-02', 'amount' => 2400000, 'vat_mode' => 'yok', 'vat_rate' => 0, 'document_kind' => 'sozlesme', 'document_no' => 'POL-2026-0012', 'description' => 'Bina ortak alan yangın sigortası (yıllık)', 'status' => 'odendi']);
        $expenses->create($b, ['category_id' => $cat('Kamera / güvenlik sistemi'), 'vendor_id' => null, 'account_id' => $banka, 'expense_date' => '2026-05-20', 'period' => '2026-05', 'amount' => 3600000, 'vat_mode' => 'dahil', 'vat_rate' => 20, 'document_kind' => 'fatura', 'document_no' => 'KAM-2026-77', 'description' => 'Kamera sistemi yenileme (demirbaş katkı payından)', 'status' => 'odendi']);
        $cancelled = $expenses->create($b, ['category_id' => $cat('Kırtasiye'), 'vendor_id' => null, 'account_id' => $kasa, 'expense_date' => Dates::today(), 'period' => $currentPeriod, 'amount' => 45000, 'vat_mode' => 'dahil', 'vat_rate' => 20, 'document_kind' => 'fis', 'description' => 'Yanlış girilen kırtasiye fişi', 'status' => 'odendi']);
        $expenses->cancel($cancelled, $b, 'Fiş başka yapıya aitti.');

        // --- Periyodik gider tanımları
        foreach ([['Kapıcı maaşı', 'Kapıcı maaşı', 2200000, 1], ['Asansör bakımı', 'Asansör aylık bakım', 450000, 5], ['Temizlik görevlisi', 'Temizlik hizmeti', 950000, 3]] as [$cn, $title, $amt, $day]) {
            $this->db->insert('recurring_expenses', ['building_id' => $b, 'category_id' => $cat($cn), 'vendor_id' => $vendors[$cn === 'Kapıcı maaşı' ? 'Elektrik' : ($cn === 'Asansör bakımı' ? 'Asansör bakımı' : 'Temizlik')] ?? null, 'account_id' => $banka, 'title' => $title, 'amount' => $amt, 'day_of_month' => $day, 'frequency' => 'aylik', 'start_period' => '2026-01', 'last_generated_period' => $currentPeriod, 'auto_paid' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->db->update('recurring_expenses', ['vendor_id' => null], 'title = ?', ['Kapıcı maaşı']);

        // --- Diğer gelirler ve virman
        $incomes->create($b, ['category_id' => $incCat('Ortak alan kira geliri'), 'account_id' => $banka, 'income_date' => '2026-03-05', 'period' => '2026-03', 'amount' => 1500000, 'description' => 'Çatı baz istasyonu kira geliri (çeyrek)', 'document_no' => null]);
        $incomes->create($b, ['category_id' => $incCat('Banka faiz geliri'), 'account_id' => $banka, 'income_date' => '2026-06-30', 'period' => '2026-06', 'amount' => 82500, 'description' => 'Vadeli hesap faiz geliri', 'document_no' => null]);
        (new TransferService($this->app))->create($b, ['from_account_id' => $banka, 'to_account_id' => $kasa, 'transfer_date' => '2026-07-02', 'amount' => 500000, 'fee_amount' => 0, 'kind' => 'nakit_cekme', 'description' => 'Kasa için nakit çekimi']);

        // --- Bütçe / işletme projesi
        $budgetId = $this->db->insert('budgets', ['building_id' => $b, 'fiscal_year' => 2026, 'version' => 1, 'title' => '2026 İşletme Projesi', 'status' => 'onayli', 'distribution' => 'grup', 'reserve_fund_amount' => 5000000, 'reserve_fund_percent' => 0, 'approved_at' => '2025-12-20 19:30:00', 'decision_no' => '2025/3', 'notes' => 'Olağan genel kurulda oy birliğiyle kabul edildi.', 'created_by' => $users['manager'], 'created_at' => $now, 'updated_at' => $now]);
        $order = 0;
        foreach ([['Kapıcı maaşı', 26400000], ['SGK primi', 9480000], ['Asansör bakımı', 5400000], ['Temizlik görevlisi', 11400000], ['Ortak alan elektriği', 3600000], ['Ortak alan suyu', 1200000], ['Bahçe bakımı', 2600000], ['Bina sigortası (DASK / yangın)', 2400000], ['Kamera / güvenlik sistemi', 3600000], ['Kırtasiye', 300000], ['Diğer onarım', 4000000]] as [$cn, $amt]) {
            $this->db->insert('budget_lines', ['budget_id' => $budgetId, 'kind' => 'gider', 'category_id' => $cat($cn), 'name' => $cn, 'annual_amount' => $amt, 'sort_order' => $order++]);
        }
        $this->db->insert('budget_lines', ['budget_id' => $budgetId, 'kind' => 'gelir', 'category_id' => null, 'name' => 'Aidat gelirleri', 'annual_amount' => 24 * 12 * 250000 - 2 * 12 * 250000 + 2 * 12 * 400000, 'sort_order' => $order++]);
        $this->db->insert('budget_lines', ['budget_id' => $budgetId, 'kind' => 'gelir', 'category_id' => $incCat('Ortak alan kira geliri'), 'name' => 'Baz istasyonu kira geliri', 'annual_amount' => 6000000, 'sort_order' => $order++]);
        (new BudgetService($this->app))->refreshSuggested($budgetId, $b);

        // --- Sayaçlar ve okumalar (su)
        $meterSvc = new MeterService($this->app);
        foreach ($units as $idx => $uid) {
            $mid = $this->db->insert('meters', ['building_id' => $b, 'unit_id' => $uid, 'type' => 'su', 'serial_no' => 'SU-' . str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT), 'multiplier' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $val = mt_rand(100, 400);
            foreach (array_slice($periods, -4) as $p) {
                $val += mt_rand(3, 14);
                $meterSvc->addReading($mid, $b, $p, Dates::dayOfPeriod($p, 25), (float) $val);
            }
        }

        // --- Talepler
        $reqs = [
            ['ariza', 'A Blok asansör 3. katta takılıyor', 'Sabah saatlerinde iki kez 3. katta kapı açılmadan bekledi.', 'yuksek', 'atandi', $users['staff'], $units[2]],
            ['temizlik', 'Otopark rampası kaygan', 'Yağmur sonrası yosun tutuyor, kayma riski var.', 'normal', 'devam', $users['staff'], $units[8]],
            ['guvenlik', 'Bahçe kapısı otomat kilidi arızalı', 'Kapı kendiliğinden kapanmıyor.', 'acil', 'yeni', null, $units[3]],
            ['oneri', 'Bisiklet park alanı', 'Otoparkın kuzey köşesine bisiklet aparatı önerisi.', 'dusuk', 'beklemede', null, $units[6]],
            ['ariza', 'B Blok giriş aydınlatması yanmıyor', 'Sensörlü armatür değişmeli.', 'normal', 'tamamlandi', $users['staff'], $units[14]],
        ];
        foreach ($reqs as $i => [$cat2, $title, $desc, $prio, $status, $assigned, $unitId]) {
            $rid = $this->db->insert('requests', ['building_id' => $b, 'unit_id' => $unitId, 'person_id' => $people[$unitId]['owner'], 'created_by' => $users['manager'], 'category' => $cat2, 'title' => $title, 'description' => $desc, 'priority' => $prio, 'status' => $status, 'assigned_to' => $assigned, 'target_date' => date('Y-m-d', strtotime('+' . (3 + $i) . ' days')), 'visibility' => $i === 3 ? 'herkes' : 'ozel', 'cost_amount' => $status === 'tamamlandi' ? 85000 : 0, 'resolved_at' => $status === 'tamamlandi' ? $now : null, 'created_at' => date('Y-m-d H:i:s', strtotime('-' . (10 - $i) . ' days')), 'updated_at' => $now]);
            $this->db->insert('request_comments', ['request_id' => $rid, 'user_id' => $users['manager'], 'body' => 'Talep alındı, ilgili görevliye iletildi.', 'is_internal' => 0, 'created_at' => $now]);
        }

        // --- Duyurular, toplantı, anket, belgeler
        $this->db->insert('announcements', ['building_id' => $b, 'title' => 'Su kesintisi: ' . Dates::trLong(date('Y-m-d', strtotime('+2 days'))), 'body' => "İSKİ bakım çalışması nedeniyle 09:00–14:00 arası su kesintisi yaşanacaktır. Depo dolu tutulacaktır.", 'target' => 'tumu', 'channel' => 'eposta', 'priority' => 'onemli', 'published_at' => $now, 'is_pinned' => 1, 'created_by' => $users['manager'], 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('announcements', ['building_id' => $b, 'title' => 'Aidat ödemelerinde IBAN açıklaması', 'body' => 'Havale/EFT yaparken açıklama alanına lütfen blok ve kapı numaranızı yazınız (örn. "A-4 Eylül aidatı"). Banka eşleştirmesi bu sayede otomatik yapılır.', 'target' => 'tumu', 'channel' => 'uygulama', 'priority' => 'normal', 'published_at' => date('Y-m-d H:i:s', strtotime('-20 days')), 'created_by' => $users['manager'], 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('announcements', ['building_id' => $b, 'title' => 'B Blok çatı onarımı', 'body' => 'B Blok çatı izolasyonu 3 iş günü sürecek; balkonları boş tutmanızı rica ederiz.', 'target' => 'blok', 'target_ref' => (string) $blockB, 'channel' => 'uygulama', 'priority' => 'normal', 'published_at' => date('Y-m-d H:i:s', strtotime('-5 days')), 'created_by' => $users['manager'], 'created_at' => $now, 'updated_at' => $now]);
        $meeting = $this->db->insert('meetings', ['building_id' => $b, 'type' => 'olagan', 'title' => '2026 Olağan Kat Malikleri Kurulu', 'meeting_date' => '2026-01-18 19:00:00', 'location' => 'Site toplantı salonu', 'agenda' => "1. Açılış ve divan seçimi\n2. 2025 faaliyet raporu ve denetim raporu\n3. 2026 işletme projesi\n4. Yönetici ve denetçi seçimi\n5. Dilek ve temenniler", 'minutes' => 'Toplantı 24 bağımsız bölümden 17 katılımla yeter sayıya ulaşarak açıldı. Faaliyet ve denetim raporları ibra edildi.', 'quorum_required' => 13, 'quorum_present' => 17, 'status' => 'yapildi', 'created_by' => $users['manager'], 'created_at' => $now, 'updated_at' => $now]);
        foreach ([['2026/1', 'Kamera sistemi yenilenmesine ve bedelinin bağımsız bölümlere eşit paylaştırılmasına oy çokluğuyla karar verildi.', 14, 2, 1], ['2026/2', '2026 işletme projesinin daire 2.500 ₺, dükkan 4.000 ₺ aylık aidatla kabulüne oy birliğiyle karar verildi.', 17, 0, 0], ['2026/3', 'Gecikme tazminatının KMK md. 20 uyarınca aylık %5 olarak uygulanmasına karar verildi.', 15, 1, 1]] as [$no, $text, $f, $a, $ab]) {
            $this->db->insert('meeting_decisions', ['meeting_id' => $meeting, 'decision_no' => $no, 'text' => $text, 'votes_for' => $f, 'votes_against' => $a, 'votes_abstain' => $ab, 'created_at' => $now]);
        }
        foreach ($units as $idx => $uid) {
            $this->db->insert('meeting_attendances', ['meeting_id' => $meeting, 'unit_id' => $uid, 'person_id' => $people[$uid]['owner'], 'attended' => $idx % 4 !== 1 ? 1 : 0, 'proxy_name' => $idx === 5 ? 'Vekaleten: eşi' : null]);
        }
        $this->db->insert('meetings', ['building_id' => $b, 'type' => 'yonetim', 'title' => 'Yönetim kurulu · çatı onarım teklifleri', 'meeting_date' => date('Y-m-d 19:30:00', strtotime('+9 days')), 'location' => 'Yönetim ofisi', 'agenda' => "1. Üç firma teklifinin karşılaştırılması\n2. Ödeme planı", 'status' => 'planlandi', 'created_by' => $users['manager'], 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('polls', ['building_id' => $b, 'question' => 'Otopark girişine plaka tanıma sistemi kurulsun mu?', 'description' => 'Maliyet yaklaşık 48.000 ₺; demirbaş fonundan karşılanacak.', 'type' => 'tek', 'options' => json_encode(['Evet, kurulsun', 'Hayır, gerek yok', 'Kararsızım'], JSON_UNESCAPED_UNICODE), 'starts_at' => date('Y-m-d 00:00:00', strtotime('-3 days')), 'ends_at' => date('Y-m-d 23:59:00', strtotime('+10 days')), 'is_anonymous' => 1, 'one_vote_per_unit' => 1, 'status' => 'acik', 'created_by' => $users['manager'], 'created_at' => $now, 'updated_at' => $now]);
        $this->db->insert('documents', ['building_id' => $b, 'category' => 'yonetim_plani', 'title' => 'Yönetim planı (2019 tadilli)', 'file_path' => 'demo/yonetim-plani.pdf', 'original_name' => 'yonetim-plani.pdf', 'mime' => 'application/pdf', 'size' => 0, 'visibility' => 'sakinler', 'uploaded_by' => $users['manager'], 'created_at' => $now]);
        $this->db->insert('documents', ['building_id' => $b, 'category' => 'isletme_projesi', 'title' => '2026 İşletme projesi (onaylı)', 'file_path' => 'demo/isletme-projesi-2026.pdf', 'original_name' => 'isletme-projesi-2026.pdf', 'mime' => 'application/pdf', 'size' => 0, 'visibility' => 'sakinler', 'uploaded_by' => $users['manager'], 'created_at' => $now]);

        // --- Dönem kapama: Ocak–Mart kapalı
        $periodsSvc = new PeriodService($this->app);
        foreach (['2026-01', '2026-02', '2026-03'] as $p) {
            $periodsSvc->close($b, $p, $users['manager'], 'Denetim sonrası kapatıldı.');
        }

        // --- Bildirimler
        (new NotificationService($this->app))->notifyUsers($b, [$users['manager'], $users['accountant']], 'Hoş geldiniz', 'Demo yapı hazır. Panelden tahsilat girebilir, borçlu listesini ve raporları inceleyebilirsiniz.', 'welcome');
        (new NotificationService($this->app))->notifyUsers($b, [$users['owner'], $users['tenant']], 'Sakin alanına hoş geldiniz', 'Borçlarınızı, makbuzlarınızı ve yapının gelir-gider özetini buradan takip edebilirsiniz.', 'welcome');

        $this->app->session()->remove('auth_user_id');
        $this->app->auth()->forgetUser();

        return "Demo verisi oluşturuldu.\n  Yapı: DEMO Çınar Sitesi (24 bölüm, 2 blok)\n  Hesaplar (şifre: " . self::PASSWORD . ")\n    demo.admin@aidat.local · demo.yonetici@aidat.local · demo.muhasebe@aidat.local\n    demo.denetci@aidat.local · demo.gorevli@aidat.local · demo.malik@aidat.local · demo.kiraci@aidat.local";
    }
}
