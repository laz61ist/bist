# Premium Admin Dashboard Temeli — Araştırma Raporu (Aidat Yönetimi, Pure PHP 8.3 MVC)

> Bu rapor arka plan araştırma ajanının çıktısıdır (19 Eylül 2026). Nihai tasarım kararı için bkz. `TASARIM.md`:
> kullanıcı "Mühürlü Hesap Defteri" yönünü seçti (Fraunces + IBM Plex Sans + IBM Plex Mono); bu rapordaki
> Inter/Geist önerisi referans olarak saklandı, uygulanmadı. Kütüphane/lisans bulguları (ApexCharts lisans
> değişikliği, Basecoat, Alpine, htmx, Chart.js, dompdf) geçerlidir.

## 0. Yöntem ve kısıtlar (dürüstlük notu)

- Kaynak evreni, görevde tanımlandığı gibi GitHub sayfaları/README'ler, npm registry ve demo/doküman sayfalarıdır. Bu oturumda **cdn.jsdelivr.net, cdnjs, unpkg, data.jsdelivr.com, tailwindcss.com, preline.co, daisyui.com, docs.tabler.io, basecoatui.com, adminlte.io, speckyboy.com, datatables.net, alpinejs.dev ve MDN egress proxy tarafından engellendi**. Bu yüzden:
  - **CDN URL doğrulaması** doğrudan GET ile değil, **npm tarball'ları indirilip içindeki dosya yolları listelenerek** yapıldı (jsDelivr `npm/` uç noktası npm paket içeriğini birebir servis eder). Aşağıdaki her URL'nin paket içinde var olduğu bu yolla doğrulandı; jsDelivr'in kendisinden HTTP 200 alınamadı.
  - Sürüm numaraları ve yayın tarihleri `registry.npmjs.org` üzerinden script ile çekildi (deterministik).
  - Yıldız / lisans / son push tarihleri GitHub Search API'den (MCP) çekildi (19 Eylül 2026 itibarıyla).
  - Yalnızca arama özetiyle görülen kaynaklar `[yalnızca özet okundu]` ile işaretlendi.
- Tam okunan kaynak sayısı: 35+ (repo sayfaları, README'ler, LICENSE dosyaları, release notları, composer.json/package.json dosyaları). Kaynak listesi sondadır.

## 1. Aday şablon / tasarım sistemleri

| Aday | Repo | Lisans | Yıldız | Son push | Teknoloji | CDN / build | Dark | RTL | Görsel (1-10) |
|---|---|---|---|---|---|---|---|---|---|
| Tabler | https://github.com/tabler/tabler | MIT | 41.7k | 2026-09-18 | Bootstrap 5.3.8 (paket içine gömülü) | CDN evet (`@tabler/core@1.5.1`) | Evet (varsayılan `auto`) | Evet (her CSS'in `.rtl` sürümü) | 8 |
| Preline UI | https://github.com/htmlstreamofficial/preline | MIT **+ Fair Use License** | 6.4k | 2026-08-31 | Tailwind v4 + headless JS plugin'leri | Build gerekli | Evet | README'de yok | 9 |
| Flowbite | https://github.com/themesberg/flowbite | MIT | 9.4k | 2026-06-27 | Tailwind v4 | CDN css+js var | Evet | Evet | 7.5 |
| Flowbite Admin Dashboard | https://github.com/themesberg/flowbite-admin-dashboard | MIT | 2.9k | 2025-03-20 | Tailwind v3.x, Hugo+webpack, ApexCharts | Build gerekli | — | — | 7.5 |
| Basecoat (shadcn/ui → HTML) | https://github.com/hunvreus/basecoat | MIT | 4.3k | 2026-07-21 | Tailwind v4 + vanilla JS | CDN evet | Evet | — | 9 |
| daisyUI | https://github.com/saadeghi/daisyui | MIT | 42.4k | 2026-09-18 | Tailwind v4 plugin | CDN evet | Evet | — | 7 |
| Mazer | https://github.com/zuramai/mazer | MIT | 3.1k | 2025-08-05 | Bootstrap 5.3.3, Vite | Build | Evet | — | 6.5 |
| Sneat Free | https://github.com/themeselection/sneat-bootstrap-html-admin-template-free | MIT | 1.2k | 2026-03-20 | Bootstrap 5 | Build | Yalnızca Pro | Yalnızca Pro | 7 |
| Materio Free | https://github.com/themeselection/materio-bootstrap-html-admin-template-free | MIT | 112 | 2025-05-14 | Bootstrap 5 | Build | Pro | Pro | 6.5 |
| Windmill | https://github.com/estevanmaito/windmill-dashboard | MIT | 3.0k | 2024-02-28 | Tailwind 1.4.6 + Alpine | Build yok | Evet | — | 6 (eskidi) |
| Notus JS | https://github.com/creativetimofficial/notus-js | MIT | 240 | 2024-07-18 | Eski Tailwind | Build yok | Hayır | — | 5.5 |
| Argon Dashboard | https://github.com/creativetimofficial/argon-dashboard | MIT | 715 | 2024-10-25 | Bootstrap 5 | Build | — | RTL sayfası var | 6.5 |
| Soft UI Dashboard | https://github.com/creativetimofficial/soft-ui-dashboard | MIT | 597 | 2024-10-25 | Bootstrap 5 (neumorfik) | Build | — | — | 6 |
| Horizon UI | https://github.com/horizon-ui/horizon-tailwind-react | MIT | 480 | 2025-01-13 | React | — | — | — | Elendi (React) |
| TailAdmin HTML | https://github.com/TailAdmin/tailadmin-free-tailwind-dashboard-template | MIT | 2.3k | 2026-09-15 | Tailwind ^4.3.3 + webpack, Alpine, ApexCharts ^7.3.0 | Build | Evet | Evet | 8 |
| Cruip Mosaic Lite | https://github.com/cruip/tailwind-dashboard-template | GPL | 2.8k | 2025-03-02 | React+Vite | — | — | — | Elendi |
| Berry Free | https://github.com/codedthemes/berry-free-bootstrap-admin-template | MIT | 18 | 2026-01-02 | Bootstrap 5 | Build | Pro | Pro | 6 |
| Mantis Free | https://github.com/codedthemes/mantis-free-bootstrap-admin-template | MIT | 11 | 2026-01-02 | Bootstrap 5 | Build | Pro | Pro | 6 |
| AdminKit | https://github.com/adminkit/adminkit | MIT | 1.7k | 2026-09-13 | Bootstrap 5.3.8, Chart.js 2.9.4 | Build | — | — | 6 |
| Volt | https://github.com/themesberg/volt-bootstrap-5-dashboard | MIT | 2.7k | 2023-03-04 | Bootstrap 5 | — | — | — | Elendi (bakımsız) |
| Franken UI | https://github.com/franken-ui/ui | MIT | 2.6k | 2026-06-14 | UIkit 3 + Lit | — | — | — | Elendi |
| Pines UI | https://github.com/thedevdojo/pines | MIT | 3.3k | 2025-08-23 | Alpine + Tailwind | Build yok | — | — | 8 (parça kaynağı) |
| HyperUI | https://github.com/markmead/hyperui | MIT | 12.2k | 2026-09-18 | Tailwind v4 kopyala-yapıştır | Build yok | Evet | Evet | 7.5 (parça kaynağı) |
| Metronic | — | Ticari | — | — | — | — | — | — | Hariç (ücretli) |

### Öne çıkanlar hakkında notlar

**Tabler.** README'ye göre her bileşen light/dark modda çalışır, her stylesheet RTL sürümüyle gelir (https://raw.githubusercontent.com/tabler/tabler/dev/README.md). v1.5.0 Bootstrap 5.3.8'i paketin içine aldı; grafik demoları ApexCharts 7.0.0'a yükseltildi (https://github.com/tabler/tabler/releases/tag/%40tabler%2Fcore%401.5.0). `tabler.min.css` 694 KB. "Tabler görünümü" çok yaygın (ör. NetBox: https://github.com/netbox-community/netbox/issues/23184) — "standart görünüm" itirazını tetikleme riski orta.

**Preline.** Lisans: MIT + "Preline UI Fair Use License" (https://github.com/htmlstreamofficial/preline/blob/main/LICENSE). README Node/npm + çalışan Tailwind projesi ister. Admin dashboard sayfaları "Pro" (ücretli). Referans olarak mükemmel, temel olarak ikinci sırada.

**Basecoat.** shadcn/ui tasarım sisteminin Tailwind + vanilla HTML/CSS/JS uygulaması; 30+ bileşen (Command, Dialog, Drawer, Sidebar, Table, Toast…), CDN CSS 218 KB (https://raw.githubusercontent.com/hunvreus/basecoat/main/README.md, https://github.com/hunvreus/basecoat/releases/tag/1.0.0). `chart.min.js` Chart.js üzerine kurulu. Basecoat CDN CSS'in tek başına hangi utility'leri içerdiği [DOĞRULANMASI GEREKİYOR].

**daisyUI.** CDN dokümanı: `daisyui@5` CSS + `@tailwindcss/browser@4` (https://github.com/saadeghi/daisyui/blob/master/packages/docs/src/routes/(routes)/docs/cdn/+page.md). 1.1 MB CSS. Estetik daha yuvarlak; Linear/Stripe hissinden uzak.

**Flowbite.** Tailwind v4, native RTL (https://raw.githubusercontent.com/themesberg/flowbite/main/README.md). Görünümü çok yaygın → "generic" riski yüksek.

**TailAdmin HTML.** v2.4.0 (2026-09-13) RTL ve i18n; ApexCharts ^7.3.0 (lisans notu aşağıda); webpack + Node 18+ şart.

**Pines / HyperUI.** Kopyala-yapıştır parça deposu; Pines'ta `command`, `slide-over`, `date-picker`, `table`, `combobox` var (https://github.com/thedevdojo/pines/tree/main/elements).

## 2. Yardımcı kütüphaneler

**Etkileşim**
- **Alpine.js 3.17.3** (2026-09-14), MIT, 31.9k yıldız (https://github.com/alpinejs/alpine). `cdn.min.js` 56 KB. Eklentiler: focus, persist, collapse, mask, anchor, intersect.
- **htmx 2.0.10** (2026-04-21), 0BSD, 49.5k (https://github.com/bigskysoftware/htmx). npm'de 4.0.0-beta yayında → üretimde 2.0.x'te kalın.

**Grafik**
- **Chart.js 4.5.1** (2025-10-13), MIT, 67.7k (https://github.com/chartjs/Chart.js). 209 KB.
- **ApexCharts 7.4.0** — **artık açık kaynak değil**: 5.1.0'dan itibaren "SEE LICENSE IN LICENSE"; Community yalnızca yıllık geliri 2M USD altındaki kuruluşlar için; başkalarının kullandığı bir ürüne gömme OEM lisansı ister (https://raw.githubusercontent.com/apexcharts/apexcharts.js/main/LICENSE; https://github.com/apexcharts/apexcharts.js/issues/5153). → **kullanmayın**.
- **Apache ECharts 6.1.0**, Apache-2.0, 1.12 MB — ağır.
- **uPlot 1.6.32**, MIT — sparkline için hafif alternatif.

**İkon**
- **Bootstrap Icons 1.13.1** (2025-05-09), MIT (https://github.com/twbs/icons). woff2 134 KB. Müşteri alışkanlığına uygun.
- **Tabler Icons 3.47.0**, MIT, webfont woff2 503 KB → webfont yerine inline SVG önerilir.
- **Lucide 1.47.0**, ISC. **Phosphor 2.1.2**, MIT.

**Form**
- **Tom Select 2.6.2** (2026-07-07), Apache-2.0. **Choices.js 11.2.4**, MIT.
- **Flatpickr 4.6.13** — son npm yayını 2022-04-14; bakımı zayıf. Alternatif: vanilla-calendar-pro 3.3.2, air-datepicker 3.6.0 veya native `<input type="date">`.
- **SortableJS 1.15.7**, MIT.

**Tablo**
- **Tabulator 6.5.3**, MIT, 448 KB; Türkçe metinler `langs` ile verilir.
- **DataTables 3.0.4**, MIT; jQuery kaldırıldı (3.0.0, 2026-07-24 [yalnızca özet okundu]); Türkçe `i18n/tr.json` (https://raw.githubusercontent.com/DataTables/Plugins/master/i18n/tr.json).
- Öneri: tabloların çoğu PHP'de render + sunucu taraflı sırala/filtrele/sayfala; yalnızca çok sütunlu grid'lerde Tabulator.

**Makbuz / PDF**
- **Tarayıcı print CSS** (`@media print`, `@page`): sıfır bağımlılık, Türkçe font sorunu yok; makbuz için ilk tercih.
- **jsPDF 4.2.1**, MIT; Türkçe karakter için TTF gömme gerekir [DOĞRULANMASI GEREKİYOR].
- **dompdf v3.1.6**, LGPL-2.1, PHP ^8.0 (https://github.com/dompdf/dompdf). Sunucu tarafı arşiv PDF'i için önerilen.
- **mPDF v8.3.1**, GPL-2.0-only → kapalı kaynak teslimde GPL riski, kaçının.
- **TCPDF** deprecated; **tc-lib-pdf** LGPL-3.0-or-later, PHP ^8.2.

**Sayı biçimleme:** `Intl.NumberFormat('tr-TR', {style:'currency', currency:'TRY'})` tarayıcıda yerleşik; PHP tarafında `NumberFormatter` (intl).

## 3. Fontlar — Türkçe glif doğrulaması

`fonts.googleapis.com/css2` yanıtlarındaki `unicode-range` blokları listelendi. **latin-ext** (ş ğ ı İ ç ö ü) şu ailelerde mevcut: Inter, Plus Jakarta Sans, Manrope, Geist, DM Sans, Geist Mono, JetBrains Mono. `@fontsource-variable/*` paketlerinde `*-latin-ext-wght-normal.woff2` dosyaları doğrulandı (OFL-1.1).

Uygulama notu (bu proje): Fraunces (variable), IBM Plex Sans (400/500/600/700) ve IBM Plex Mono (400/500/600) `@fontsource` paketlerinden indirildi; her birinde `latin-ext` alt kümesi ve OFL-1.1 lisansı doğrulandı (`public/assets/vendor/fonts/*/LICENSE`).

## 4. ÖNERİ (ajanın önerisi)

### (a) Tasarım temeli
Ajanın önerisi: Basecoat (shadcn dili) + kendi token katmanı + Tailwind v4 standalone CLI (Node'suz) + Alpine.js + htmx; şablonlar yalnızca görsel referans. Play CDN "development purposes only" (https://tailwindcss.com/docs/installation/play-cdn [yalnızca özet okundu]); standalone CLI tek ikili dosya (https://github.com/tailwindlabs/tailwindcss/releases/expanded_assets/v4.3.3).

**Proje kararı:** Tailwind yerine **el yapımı CSS tasarım sistemi** (`public/assets/css/app.css`, CSS custom properties) seçildi. Gerekçe: (1) sıfır build adımı ve sıfır CDN bağımlılığı (paylaşımlı hosting), (2) kullanıcının seçtiği "Mühürlü Hesap Defteri" yönü herhangi bir hazır kit görünümünden bilinçli olarak uzak, (3) tek CSS dosyası, tam kontrol. Alpine.js (vendor'lanmış) yerel UI durumu için; htmx eklenmedi (sunucu taraflı form + tam sayfa yönlendirme yeterli).

### (b) Sabitlenmiş CDN URL'leri (ajan listesi; projede self-host kullanıldı)
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/basecoat-css@1.0.2/dist/basecoat.cdn.min.css">
<script src="https://cdn.jsdelivr.net/npm/basecoat-css@1.0.2/dist/js/all.min.js" defer></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.17.3/dist/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.10/dist/htmx.min.js" integrity="sha384-H5SrcfygHmAuTDZphMHqBJLc3FhssKjG7w/CeCpFReSfwBWDTKpkzPP8c+cLsK+V" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/css/tom-select.min.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/tr.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.5.3/dist/js/tabulator.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.23/dayjs.min.js"></script>
```
Tümü npm tarball içeriğiyle doğrulandı; jsDelivr'e erişim bu oturumda engelliydi; canlıya almadan önce `curl -I` ile 200 kontrolü yapın.

### (c) Design token önerisi (ajan)
- Tipografi: Inter + Geist Mono; ölçek 12/13/14/16/20/24/30; `font-variant-numeric: tabular-nums`.
- Renk (OKLCH): Derin petrol `oklch(0.52 0.10 200)`, lacivert-indigo `oklch(0.45 0.12 265)`; semantik başarı `#16A34A`, uyarı `#D97706`, hata `#DC2626`, bilgi `#0284C7`.
- Yarıçap `0.625rem`; gölge ölçeği xs/sm/md/lg; kart kenarı hairline + yumuşak gölge.
- Dark mode: `<html data-theme>` + `prefers-color-scheme`; tercih localStorage; koyu yüzeyler saf siyah değil.

### (d) Premium hissi veren imza desenler (12)
1. ⌘K komut paleti. 2. Yapışkan cam başlık + breadcrumb + sayfa eylemleri. 3. KPI kartlarında sparkline + delta pill. 4. Veri tablosu disiplini (sağa hizalı tabular-nums, hover eylemleri, yapışkan başlık). 5. Form için modal yerine slide-over drawer. 6. Toast sistemi. 7. İskelet yükleyiciler. 8. Boş durumlar (başlık + cümle + CTA). 9. Aktivite zaman çizelgesi. 10. Avatar yığını ve noktalı durum pill'i (renk körü dostu). 11. Klavye kısayolları. 12. Makbuz için print/PDF katmanı (`@page A5`).

## 5. Kaynaklar (tam okunanlar)
Repo/README/LICENSE: tabler/tabler, htmlstreamofficial/preline, hunvreus/basecoat, themesberg/flowbite, themesberg/flowbite-admin-dashboard, saadeghi/daisyui, zuramai/mazer, themeselection/sneat-…-free, themeselection/materio-…-free, estevanmaito/windmill-dashboard, TailAdmin/tailadmin-free-tailwind-dashboard-template, cruip/tailwind-dashboard-template, franken-ui/ui, creativetimofficial/notus-js, creativetimofficial/argon-dashboard, codedthemes/berry-free-bootstrap-admin-template, codedthemes/mantis-free-bootstrap-admin-template, horizon-ui/horizon-tailwind-react, thedevdojo/pines, markmead/hyperui, adminkit/adminkit, apexcharts/apexcharts.js (LICENSE, issue #5153), tailwindlabs/tailwindcss, alpinejs/alpine, bigskysoftware/htmx, lucide-icons/lucide, dompdf/dompdf, mpdf/mpdf, tecnickcom/TCPDF, tecnickcom/tc-lib-pdf, DataTables/Plugins, tc39/ecma402. Registry/API: registry.npmjs.org, fonts.googleapis.com css2, GitHub Search API.

[yalnızca özet okundu]: tailwindcss.com/docs/installation/play-cdn, datatables.net/blog/2026/datatables-3, tabler.io/blog/tabler-1.5, apexcharts.com/blog/new-licencing-model.

**Anayasa notu:** Kaynak evreni görevde verilen listeydi (GitHub/npm/demo); evren dışı kaynak kullanılmadı. CDN sunucularına erişim engeli nedeniyle "URL'yi fetch ederek doğrula" talebi npm tarball yöntemiyle karşılandı ve bu açıkça belirtildi.
