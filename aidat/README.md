# Aidat Yönetim Sistemi

Apartman ve konut siteleri için aidat, borç, tahsilat, makbuz, gelir-gider, bütçe, sayaç, talep, duyuru ve denetim yazılımı. Yönetici, muhasebe, denetçi, görevli, malik ve kiracı aynı kayıt üzerinde çalışır; her finansal işlem izlenebilir ve sakinlere şeffaftır. **Kredi kartı / sanal POS yoktur**; nakit, havale/EFT, çek, mahsup ve banka ekstresi eşleştirme vardır.

- Stack: **Pure PHP 8.3 MVC** (framework yok), PDO, SQLite (sıfır kurulum) veya MySQL/MariaDB, Alpine.js, Chart.js. Tüm varlıklar self-host; CDN yok.
- Tasarım: "Mühürlü Hesap Defteri" — Fraunces + IBM Plex Sans/Mono, kâğıt-mürekkep paleti, açık/koyu tema, mobil alt çubuk. Bkz. `docs/TASARIM.md`.
- Belgeler: `docs/ARASTIRMA.md` (menü/form/droplist/rapor/makbuz/mevzuat spesifikasyonu), `docs/ARASTIRMA-URUNLER.md`, `docs/ARASTIRMA-UI.md`, `docs/GELISTIRME-REHBERI.md`, `docs/KURULUM.md`.

## Hızlı başlangıç
```bash
cd aidat
cp .env.example .env && php bin/aidat key:generate   # APP_KEY'i .env'e yazın
php bin/aidat migrate && php bin/aidat seed          # demo veri; şifre Demo1234!
php bin/aidat serve                                  # http://127.0.0.1:8090
```
Demo: `demo.yonetici@aidat.local` (yönetim), `demo.malik@aidat.local` (sakin alanı), `demo.denetci@aidat.local` (salt okunur).

## Testler
```bash
composer install && ./vendor/bin/phpunit      # birim testleri (bellek içi SQLite)
tests/smoke.sh                                # tüm GET rotaları (çalışan sunucu)
tests/smoke-portal.sh                         # sakin alanı + yetki sınırı
```

## Konsol
`php bin/aidat migrate | seed | user:create | dues:generate | recurring:generate | latefee:apply | reminders:send | backup | serve`

## Lisanslar
Uygulama Apache-2.0. Vendor: Alpine.js (MIT), Chart.js (MIT), Bootstrap Icons (MIT), qrcode-generator (MIT), Fraunces / IBM Plex Sans / IBM Plex Mono (OFL-1.1) — lisans dosyaları `public/assets/vendor/**`.
