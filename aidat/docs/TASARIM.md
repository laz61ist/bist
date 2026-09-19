# Tasarım Sistemi — "Mühürlü Hesap Defteri"

Seçilen yön (kullanıcı kararı, 19 Eylül 2026): **sıcak basılı kâğıt ve mürekkep.** Bir yönetim defteri hissi; grafik duvarı değil, eylem ve istisna odaklı tablolar.
Rapor ve araştırma karşılaştırması için `ARASTIRMA-UI.md` (ajan raporu) saklandı; oradaki Basecoat/Tailwind önerisi uygulanmadı, el yapımı CSS tercih edildi (sıfır build, sıfır CDN).

## Neden hazır şablon değil
- Tabler/AdminLTE/Flowbite görünümü tanınır ("standart"); kullanıcı bunu açıkça istemedi.
- Paylaşımlı hosting hedefi: Node/Tailwind derleme adımı istenmiyor. Tek `app.css` (≈600 satır) + Alpine.js.
- ApexCharts lisans değişikliği (source-available, OEM şartı) → Chart.js (MIT).

## Tipografi
| Rol | Font | Dosya | Lisans |
|---|---|---|---|
| Başlıklar, marka, mühür | Fraunces (variable, wght) | `public/assets/vendor/fonts/fraunces/` (latin + latin-ext) | OFL-1.1 |
| Metin, form, tablo | IBM Plex Sans 400/500/600/700 | `public/assets/vendor/fonts/ibm-plex-sans/` | OFL-1.1 |
| Rakam, tutar, IBAN, makbuz no | IBM Plex Mono 400/500/600 (`tabular-nums`) | `public/assets/vendor/fonts/ibm-plex-mono/` | OFL-1.1 |

Türkçe glifler (ş ğ ı İ ç ö ü) `latin-ext` alt kümesiyle doğrulandı. Gövde 14 px, satır 1.5; başlık ölçeği 28 / 21 / 17 / 15.

## Renk tokenları (`:root`, koyu tema `[data-theme=dark]`)
| Token | Açık | Anlam |
|---|---|---|
| `--bg` / `--surface` | `#F3EFE6` / `#FBF9F4` | kırık kâğıt zemin / kart |
| `--ink` / `--ink-2` / `--ink-3` | `#1D1B17` / `#4B4740` / `#7B756A` | mürekkep; metin hiyerarşisi |
| `--rule` / `--rule-strong` | `#DCD4C4` / `#C6BBA5` | ince cetvel çizgileri |
| `--moss` | `#4E6C3A` | olumlu: ödendi, geçerli, tahsilat |
| `--clay` | `#A8432E` | borç, vadesi geçmiş, iptal |
| `--amber` | `#B7852A` | vurgu, aktif menü, uyarı |
| `--slate` | `#5B6B7A` | bilgi |
| `--seal` | `#8F2E28` | mühür kırmızısı (onay damgaları) |

Kural: **renk tek başına durum anlatmaz.** Her `.pill` nokta + metin taşır; grafiklerde efsane vardır; iptal satırları üstü çizilidir.

## Biçim ve doku
- Köşe yarıçapı 4 / 6 / 8 px. Gölge yok denecek kadar az; ayrım hairline çizgiyle.
- Zemin: `feTurbulence` tabanlı %5 opaklıkta kâğıt dokusu + hafif kehribar vinyet (SVG data URI, dış kaynak yok).
- Mühür (`.seal`): çift kenarlı daire, −12° döndürülmüş, Fraunces büyük harf. Makbuzda "GEÇERLİ / İPTAL", giriş sayfasında marka.
- Ekstre (`.ledger`, `.balance-box`): çizgili defter zemini, mono rakamlar, borç kil / alacak yosun.

## Yerleşim
- Masaüstü: 264 px daraltılabilir sol menü (72 px ikon modu, tercih localStorage), 60 px yapışkan cam üst çubuk (yapı seçici, ⌘K arama, bildirim, tema, hesap).
- Mobil (≤960 px): menü off-canvas; 5 düğmeli alt eylem çubuğu (Panel · Bölümler · **Tahsilat** · Borçlu · Menü). 390 px'te yatay taşma yok (Playwright ile doğrulandı).
- Sayfa başı: kırıntı + Fraunces başlık + açıklama + sağda eylemler.

## Bileşen envanteri (`app.css`)
Kart (`.card`, head/body/foot, `accent-*`), KPI (`.stat`, `.stat-row`, `.balance-box`), ilerleme, rozetler (`.pill.ok|warn|bad|info|neutral`, `.badge`, `.tag`, `.seal`), butonlar (`.btn`, `-primary`, `-danger`, `-ghost`, `-amber`, `-sm`, `.btn-group`), form (`.form-grid` 12 kolon, `.field.cN`, `.input/.select/.textarea`, `.input-group .addon`, `.check`, `.switch`, `.radio-cards`, `.file-drop`), tablo (`.table`, `.table-toolbar`, `.row-actions`, `.is-cancelled`, `.pagination`, `.bulk-bar`), tanım listesi (`.dl`), uyarı (`.alert`), boş durum (`.empty`), sekme (`.tabs`), zaman çizelgesi (`.timeline`), açılır menü (`.menu`), çekmece (`.drawer`), modal (`.modal`), toast (`.toasts`), komut paleti (`.cmdk`), bildirim paneli, yazdırma sayfası (`.print-sheet`, makbuz sınıfları, `@media print`), giriş ekranı (`.auth`), hata sayfası, iskelet yükleyici.

## Etkileşim (Alpine.js, `app.js`)
- Tema döngüsü sistem → açık → koyu; menü daraltma; ⌘K komut paleti (sayfalar + sunucu araması: bölüm, kişi, makbuz, tedarikçi); `g d`, `g t`, `n t` kısayolları; `?` kısayol penceresi.
- Toast (flash mesajlarından), gerekçeli iptal modalı (`reasonModal`), onaylı form (`confirmForm`), satır tıklaması (`tr[data-href]`), filtre otomatik gönderim, toplu seçim.
- Tutar alanları: `data-money` → blur'da `1.250,50` biçimi; JS tarafında da Türkçe/İngilizce ayraç çözümü (sunucu `Money::parse` ile aynı kural).
- Grafik: `canvas[data-chart]` JSON konfigürasyonu, tema renkleri CSS değişkenlerinden okunur, para eksenleri Türkçe biçimlenir.
- QR: `qrcode-generator` ile makbuz doğrulama bağlantısı (kişisel veri içermeyen doğrulama sayfası).

## Erişilebilirlik
Odak halkası (kehribar, 3 px), `aria-current`, `aria-label` ikon düğmelerinde, `prefers-reduced-motion` desteği, `prefers-color-scheme` ile otomatik koyu tema, tabular rakamlarla hizalı sütunlar, renk körü dostu durum etiketleri.

## Premium hissi veren imza desenler (uygulananlar)
1. Mühür damgaları (makbuz, giriş, durum). 2. ⌘K komut paleti. 3. Cam üst çubuk + yapı seçici. 4. Defter/ekstre görünümü. 5. Yazdırılabilir A5 makbuz, iki nüsha, kesim çizgisi, QR. 6. KPI kartlarında ilerleme çubuğu ve net delta. 7. Zaman çizelgesi ile işlem izi. 8. Toplu bölüm sihirbazında canlı önizleme. 9. Tahsilatta canlı borç eşleştirme önizlemesi. 10. Gerekçeli ters kayıt modalı. 11. Sakin alanı alt eylem çubuğu. 12. Boş durumlar CTA ile.
