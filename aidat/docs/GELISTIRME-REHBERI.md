# Geliştirme Rehberi — Aidat Yönetim Sistemi

Pure PHP 8.3 MVC, framework yok. Bu belge modül geliştiren herkes (ve paralel çalışan ajanlar) için **bağlayıcı sözleşmedir**.

## 1. Dizin yapısı

```
aidat/
  public/index.php            tek giriş; assets/ (css/app.css, js/app.js, vendor/)
  app/bootstrap.php           autoload (composer yoksa yerleşik PSR-4), .env, Application
  app/routes.php              TÜM rotalar (adlandırılmış). DEĞİŞTİRMEYİN; eksik rota gerekiyorsa raporlayın.
  app/Core/                   Application, Router, Request, Response, Controller, View, Form, Validator,
                              Database, Blueprint/Schema/Migrator, Session, Csrf, Auth, Gate, Audit, Settings,
                              Uploads, Signer, Money, Dates, Str, Paginator, Exporter, Logger
  app/Http/Middleware/        auth, guest, csrf, building, can:perm, admin, portal
  app/Controllers/            Auth/, Manager/ (yönetim alanı), Portal/ (sakin alanı), Shared/
  app/Services/               iş kuralları (Charge, Payment, LateFee, Ledger, Expense, Income, Transfer, Budget,
                              Meter, Notification, Import, Backup, Period, Receipt, RecurringExpense)
  app/Views/                  layouts/, partials/, auth/, shared/, manager/<modul>/, portal/, errors/
  config/                     app, database, permissions, lists (TÜM droplist değerleri), menu
  database/migrations/        YYYY_MM_DD_NNN_aciklama.php   database/Seeds/DemoSeeder.php
  storage/                    database/, uploads/ (özel), logs/, backups/  (git dışı)
  tests/                      PHPUnit
```

## 2. Kesin kurallar

- `declare(strict_types=1);` her dosyada. PSR-12. `final class`.
- DB alan adları İngilizce snake_case; UI metinleri Türkçe. Hata mesajları Türkçe.
- **Para = tam sayı kuruş (int).** Asla float. Ekranda `money($kurus)` → `1.250,50 ₺`. Formdan gelen tutar `money` doğrulama kuralıyla kuruşa çevrilir (`'amount' => 'required|money_positive'`). Form alanı `Form::money('amount', 'Tutar', ['value' => $kurus])`.
- Tarihler DB'de `YYYY-MM-DD`; ekranda `tr_date()`; dönem `YYYY-MM` → `tr_period()`.
- Tüm sorgular `$this->db->fetch/fetchAll/insert/update/delete` ile prepared statement. String birleştirmeyle değer gömme YOK (LIMIT/OFFSET için `Paginator` değerleri int).
- **Yapı izolasyonu:** her sorgu `building_id = $this->buildingId()` ile sınırlanır. Tekil kayıt için `$this->findOwned('tablo', $id)` (yoksa 404). Başka yapının verisi asla görünmez.
- **Yetki:** rota düzeyinde `can:` ara katmanı var; controller içinde ek denetim için `$this->authorize('payments.cancel')`. Görünümde buton gizlemek için `can('...')`. Ekran gizlemek güvenlik değildir.
- **Finansal kayıt silinmez.** İptal = `status = 'iptal'` + gerekçe + ters kayıt (servisler yapar). Silme yalnızca finansal olmayan tanımlar için (blok, araç, taslak plan satırı vb.).
- **Kapalı dönem:** finansal servisler `PeriodService::assertOpen()` çağırır; controller'da ayrıca gerekmez.
- **İşlem izi:** her yazma işleminde `$this->audit('modul.eylem', 'entity', $id, $old, $new, 'Özet')`. Servisler kendi izlerini yazar; controller yalnızca servis dışı yazımlarda çağırır.
- CSRF: tüm POST formlarında `<?= csrf_field() ?>`. Silme/iptal işlemleri GET ile YAPILMAZ.
- Dosya yükleme: `$this->app->uploads()->store($this->request->file('file'), 'giderler/' . $buildingId)` → JPG/PNG/PDF, 5 MB, uniqid; `documents` tablosuna kaydedin (`entity_type`, `entity_id`). İndirme yalnızca `route('documents.download', ['id' => $docId])` (yetki denetimli).
- Bootstrap Icons (`icon('cash-coin')` veya `<i class="bi bi-…">`). FontAwesome YOK. Harici CDN YOK.

## 3. Controller şablonu

```php
final class ExpenseController extends Controller
{
    public function index(): Response
    {
        $b = $this->buildingId();
        $p = $this->paginator($total, 30);           // ?sayfa=, ?adet=
        return $this->view('manager.expenses.index', ['title' => 'Giderler', 'rows' => $rows, 'p' => $p] + $this->lists('expense_statuses'));
    }
    public function store(): Response
    {
        $d = $this->validate([...kurallar...], [...türkçe etiketler...]);   // hata → geri yönlendirme + old() + errors()
        $id = (new ExpenseService($this->app))->create($this->buildingId(), $d);
        $this->success('Gider kaydedildi.');
        return $this->redirectRoute('expenses.show', ['id' => $id]);
    }
}
```
- İş kuralı ihlali için `throw new DomainException('Türkçe mesaj')` → otomatik flash + geri dönüş.
- `$this->request->str('q')`, `->int('sayfa')`, `->bool('x')`, `->has('yazdir')`, `->file('dosya')`.
- Yardımcılar: `$this->building()`, `$this->user()`, `$this->userId()`, `$this->setting('late_fee_rate')`, `$this->can()`, `$this->back()`.
- Doğrulama kuralları: required, nullable, string, email, min, max, integer, numeric, money, money_positive, gte:alan, date, period, after_or_equal:alan, in:a,b, in_keys:lists.liste_adi, boolean, confirmed, regex, phone, iban, tckn, tckn_or_vkn, url, array, unique:tablo,sutun,ignoreId, exists:tablo,sutun.
- Yazdırılabilir sayfa: `Response::html($this->app->view()->render('manager.x.print', $data + ['backUrl' => ..., 'title' => ...], 'layouts.print'))`.
- CSV/XLSX: `Response::download(Exporter::csv($headers, $rows), 'dosya.csv', 'text/csv; charset=utf-8')`; `Exporter::xlsxAvailable()` ise `Exporter::xlsx(...)` ile `.xlsx` (mime `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`). Para sütunlarında `Money::decimal($kurus)` (CSV) veya `(int) $kurus / 100` float (XLSX sayısal).

## 4. Görünüm şablonu

Her sayfa `app/Views/manager/<modul>/<sayfa>.php`; layout otomatik `layouts.app`. Sayfa başı:
```php
<?php use Aidat\Core\Form; ?>
<?= $this->partial('partials.page-head', ['title' => 'Giderler', 'desc' => 'Kısa açıklama', 'crumbs' => [['Giderler', route('expenses.index')], ['Yeni']], 'actions' => '<a class="btn btn-primary" href="…"><i class="bi bi-plus-lg"></i>Yeni gider</a>']) ?>
```
Bileşenler (bkz. `public/assets/css/app.css`):
- Kart: `.card > .card-head (h3 + .sub + .tools) / .card-body / .card-foot`. Tablo kartı: `.card > form.table-toolbar + .table-wrap > table.table + partials.pagination`.
- Tablo: `th.num/td.num` sağa hizalı tabular rakam; satır `data-href="url"` tıklanabilir; `.row-actions` hover eylemleri; `tr.is-cancelled` iptal satırı; `tfoot` toplam; `.primary-cell` + `.sub-cell`.
- Durum: `<span class="pill <?= status_tone($status) ?>"><?= list_label('expense_statuses', $status) ?></span>` (renk + metin; asla yalnız renk).
- KPI: `.stat-row > .stat (.k .v .d)`, `.balance-box`. Boş durum: `partials.empty`. Uyarı: `.alert.ok|warn|bad|info`.
- Form: `.form-grid` içinde `Form::text|email|password|number|date|month|datetime|money|textarea|select|checkbox|radioCards|file(name, label, opts)`; `opts`: value, required, col ('c6','c4','c3'…), help, placeholder, options (select için 3. parametre; optgroup için iç dizi), attrs (ham attribute, örn. `x-model="x"`), readonly, min/max/step. Eski girdi ve hata mesajları otomatik.
- Form kapanışı: `<div class="form-actions"><a class="btn" href="…">Vazgeç</a><button class="btn btn-primary"><i class="bi bi-check2"></i>Kaydet</button></div>`.
- İptal/ters kayıt: kapsayıcıya `x-data="reasonModal"`, buton `@click="ask('<?= e(route('x.cancel', ['id' => $id])) ?>', 'Başlık', 'Gerekçe')"` ve sayfada bir kez `<?= $this->partial('partials.reason-modal') ?>`. Controller `reason` alanını `required|min:3` doğrular.
- Onaylı basit POST: `<form method="post" x-data="confirmForm('Emin misiniz?')" @submit="submit($event)">`.
- Filtre formu: `<form class="table-toolbar" method="get" data-autosubmit>` (select değişince gönderir). Sayfalama bağlantıları mevcut sorguyu korur (`query_with([...])`).
- Grafik: `<div class="chart-box"><canvas data-chart='<?= e(json_encode([...Chart.js config..., 'money' => true])) ?>'></canvas></div>`; renk için dataset'e `'color' => 'moss'|'clay'|'amber'|'ink'|'slate'|'ink4'`.
- Yardımcı fonksiyonlar: `e() route() money() money_input() tr_date() tr_datetime() tr_period() old() error_for() csrf_field() can() is_admin() list_label() list_options() icon() status_tone() number_tr() percent_tr() query_with() setting() flash()`; `\Aidat\Core\Str::initials|formatPhone|maskIdentity|formatIban|limit`; `\Aidat\Core\Dates::ago|monthsBetween|periodRange|addMonths|dayOfPeriod`.
- Mobil: 390 px'te taşma olmamalı; geniş tablolar `.table-wrap` içinde; ikincil sütunlara `.hide-sm`.
- Referans örnekler: `manager/units/index.php` (liste+filtre), `manager/units/form.php` (form), `manager/units/show.php` (detay+modal), `manager/units/statement-print.php` (yazdır), `manager/dashboard/index.php` (KPI+grafik).

## 5. Servis API özeti (kullanın, yeniden yazmayın)

- `ChargeService`: `planUnits($b, $blockId, $groupId)`, `distribute($plan, $units, $manual, $includedIds)` → `[unit_id => ['amount','share']]`, `savePlan($b, $data, $lines, $planId=null)`, `approvePlan`, `processPlan($planId, $b, $period=null)`, `generateRecurringPlans`, `cancelPlan($id,$b,$reason)`, `createCharge($b, $data)`, `cancelCharge($id,$b,$reason)`, `unitBalance($unitId)` → debt/advance/overdue/balance, `openCharges($unitId)`, `buildingDebtSummary($b)`, `debtors($b, $asOf, $blockId, $minDays)` (yaşlandırma d30/d60/d90/d90p), `responsiblePerson`, `responsibleName`.
- `PaymentService`: `create($b, [unit_id, account_id, payment_date, amount, method, reference_no, description, allocation_mode eski|manuel|avans, manual[charge_id=>kuruş]])`, `cancel($id,$b,$reason,$refund=false)`, `applyAdvances($unitId)`, `allocationsOf($paymentId)`, `statement($unitId,$from,$to)`, `receiptData($paymentId,$b)` (makbuz için tüm alanlar + `allocations`, `payer_name`, `amount_words`, `verify_code`).
- `LateFeeService`: `rule($b)`, `preview($b,$asOf,$unitId=null)`, `apply($b,$asOf,$unitId=null)`.
- `LedgerService`: `balances($b)`, `balance($accountId,$asOf)`, `statement($accountId,$from,$to)`, `record(...)`, `reverse(...)`, `defaultAccountId($b,'kasa'|'banka')`.
- `ExpenseService`: `vat()`, `create($b,$d)`, `update($id,$b,$d)`, `pay($id,$b,$accountId,$amount,$date,$ref)`, `cancel($id,$b,$reason)`, `find`, `byCategory($b,$from,$to)`, `categoryOptions($b)` (optgroup dizisi), `ensureDefaultCategories($b)`.
- `IncomeService`: `create`, `cancel`. `TransferService`: `create`, `cancel`. `RecurringExpenseService::generate($period,$b)`.
- `BudgetService`: `totals($budgetId,$b)`, `refreshSuggested`, `actuals($budgetId,$b)`.
- `MeterService`: `addReading($meterId,$b,$period,$date,$value,$notes)`, `distribute($b,$type,$period,$totalKurus,$fixedPercent,$dueDate)` → taslak plan.
- `NotificationService`: `notifyPeople($b,$personIds,$subject,$body,$channels,$template,$refType,$refId)`, `notifyUsers`, `audiencePeople($b,$target,$targetRef)`, `sendDebtReminders($b)`, `sendEmail`, `sendSms`.
- `ImportService`: `importCsv($b,$accountId,$tmpPath,$fileName)`, `match($rowId,$b,$unitId,$method)`, `ignore($rowId,$b)`.
- `PeriodService`: `isClosed`, `assertOpen`, `close($b,$period,$userId,$note)`, `reopen`, `yearMap($b,$year)`. `BackupService`: `create()`, `list()`. `ReceiptService`: `next`, `verifyCode`, `decodeVerifyCode`.

## 6. Yerel çalıştırma ve test

```
php bin/aidat migrate && php bin/aidat seed         # demo veri (şifre Demo1234!)
php -S 127.0.0.1:8090 -t public public/index.php    # veya php bin/aidat serve
# giriş + sayfa testi (curl):
curl -s -c cj -b cj http://127.0.0.1:8090/giris | grep -o 'name="_token" value="[^"]*"'
curl -s -c cj -b cj -X POST -d '_token=TOKEN&email=demo.yonetici@aidat.local&password=Demo1234!' http://127.0.0.1:8090/giris
curl -s -b cj -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8090/yonetim/giderler
tail storage/logs/app-*.log                          # 500 hatalarının ayrıntısı burada
```
Kabul: her rota 200/302 döner, HTML'de "Warning/Notice" yok, formlar hatalı girdide Türkçe mesajla geri döner, tüm listelerde boş durum vardır, 390 px'te taşma yoktur.
