# bist

Borsa İstanbul analiz sistemi — skill'ler, dokümantasyon ve yayın altyapısı.

Kaynak paket: Eren Gül Aydın, *Borsa İstanbul Analiz Sistemi* (Apache 2.0).
Lisans ve atıflar: [THIRD-PARTY.md](THIRD-PARTY.md).

## Kokpit uygulaması

PHP 8.3+ MVC, sqlite, sıfır JS bağımlılığı. Kokpit tek HTML dosyası olarak üretilir.

```bash
composer install
E2E_DB_PATH=/tmp/bist.sqlite php -S 127.0.0.1:8200 -t public tests/E2E/AppServerRouter.php
```

`http://127.0.0.1:8200/` — kokpit · `/saglik` — durum · `/kriter` — kriter setleri (GET/POST)

**Veri kaynağı bağlı değil.** Bu kasıtlıdır: temsili veri üretilmez (G-15).
Her metrik `—` gösterir, nedenini ve kullanıcının yapabileceği eylemi yazar.
Gerçek veri için `Bist\Data\VeriKaynagi` arayüzünü uygulayan bir sınıf bağlanır.

### Neyi garanti eder

| Kural | Nerede |
|---|---|
| Kaynaksız veya dönemsiz rakam kurulamaz | `Metrik` yapıcısı istisna atar |
| Dönemlerin TMS 29 durumu farklıysa büyüme **hesaplanmaz** | `Tms29Kontrol` |
| Değer işaret değiştirdiyse yüzde üretilmez, mutlak fark döner | `Tms29Kontrol` |
| Payda ≤ 0 ise oran tanımsızdır | `Oran` |
| Eşik tanımlanmadan tarama çalışmaz | `KriterSeti` |
| Veri eksikse "geçti/kaldı" denmez, ayrı gruba girer | `KriterTarama` |
| Çıktıda al/sat/hedef fiyat/niteleyici sıfat yok | test ile sabitlenmiş |

## Yapı

```
.claude/skills/          29 skill (aşağıdaki tabloya bak)
.claude/settings.json    Claude Code izin ayarları (allow / deny / ask)
.specify/                spec-kit altyapısı (şablonlar, scriptler, workflow)
.github/workflows/       Pages yayın hattı (test -> build -> deploy)
docs/                    Markdown kaynaklar (Pages bunları yayınlar)
server/render.py         Markdown -> HTML render katmanı (ortak)
server/app.py            Yerel docs sunucusu
tools/zip_to_md.py       ZIP içinden .md çıkarır
tools/install_bist_skills.py  docs/ -> .claude/skills/ kurulumu
tools/build_site.py      Statik site üreticisi (Pages için)
tools/make_skill_zips.py claude.ai arayüzüne yüklenebilir skill zip'leri
dist/                    Üretilen skill zip'leri (arayüze yüklemek için)
tools/test_*.py          Testler
incoming/                Ham ZIP dosyaları (git'e girmez)
licenses/                Üçüncü taraf lisans metinleri
```

## Skill'ler

| Grup | Adet | Kaynak |
|---|---|---|
| `bist-*` | 5 | BIST sistem paketi (Apache 2.0) |
| `speckit-*` | 10 | [github/spec-kit](https://github.com/github/spec-kit) (MIT) |
| TDD, debugging, plan, review vb. | 14 | [obra/superpowers](https://github.com/obra/superpowers) (MIT) |

BIST skill'leri: `bist-arastirmaci` (yönetici), `bist-veri-hiyerarsisi`,
`bist-enflasyon-kontrolu`, `bist-emsal-analizi`, `bist-kriter-taramasi`.

## ZIP -> Markdown

```bash
python3 tools/zip_to_md.py incoming/paket.zip           # docs/paket/ altına çıkar
python3 tools/zip_to_md.py --all                        # incoming/ altındaki tümü
python3 tools/zip_to_md.py incoming/paket.zip --flat     # klasör yapısını düzleştir
python3 tools/zip_to_md.py incoming/paket.zip --commit   # çıkar + commit
```

Davranış:
- Sadece `.md`, `.markdown`, `.mdown`, `.mkd` alınır.
- Mutlak yol ve `..` içeren girişler (zip-slip) reddedilir; `__MACOSX`, `.DS_Store`, `.git` atlanır.
- Aynı isim + aynı içerik varsa atlanır (tekrar çalıştırmak kopya üretmez).
- Aynı isim + farklı içerik varsa `-2`, `-3` eklenir (`--overwrite` ile değiştirilebilir).
- Türkçe dosya adları: UTF-8 bayrağı yoksa cp437 -> utf-8 / cp1254 / cp857 sırasıyla çözülür.

## Yerel sunucu

```bash
pip install -r server/requirements.txt
python3 server/app.py                     # http://127.0.0.1:8000
```

## GitHub Pages

`.github/workflows/pages.yml` `main`'e her push'ta çalışır: önce testler, sonra
`docs/` -> `_site/` statik derleme, sonra yayın.

GitHub tarafında tek ayar: **Settings -> Pages -> Build and deployment -> Source: GitHub Actions**.

Bağlantılar görelidir; site `https://<kullanıcı>.github.io/bist/` alt yolunda çalışır.

## claude.ai arayüzüne skill yükleme

Depodaki `.claude/skills/` düzeni **Claude Code** oturumlarında otomatik çalışır.
claude.ai sohbeti veya Cowork için skill'lerin arayüzden ayrıca yüklenmesi gerekir
(Ayarlar -> Capabilities -> Skills -> Upload skill).

Kurulum rehberi "zip içinde tek `SKILL.md` olmalı, klasör olmamalı" diyor.
`dist/` altındaki zip'ler tam olarak bu yapıda, doğrudan yüklenebilir:

| Zip | Skill |
|---|---|
| `dist/bist-arastirmaci.zip` | Yönetici, sırayı kurar |
| `dist/bist-veri-hiyerarsisi.zip` | Kaynak sırası, uydurmayı engeller |
| `dist/bist-enflasyon-kontrolu.zip` | TMS 29 düzeltme kontrolü |
| `dist/bist-emsal-analizi.zip` | Aynı tanımlarla karşılaştırma |
| `dist/bist-kriter-taramasi.zip` | Kullanıcı eşiklerine göre eleme |

Yeniden üretmek için:

```bash
python3 tools/make_skill_zips.py
```

## Test

```bash
python3 tools/test_zip_to_md.py     # zip -> md
python3 tools/test_site.py          # render + statik site
python3 tools/test_skill_zips.py    # skill zip yapısı
./vendor/bin/phpunit                # 92 birim testi
./tests/E2E/calistir.sh             # 27 E2E kontrolü + ekran görüntüsü
```

E2E kendi sunucusunu ayağa kaldırır, temiz sqlite kullanır, Playwright ile
sayfayı gezip PNG alır ve sonunda temizler.

## Rehber ve araştırma

- [`docs/REHBER-SAYFA-HARITASI.md`](docs/REHBER-SAYFA-HARITASI.md) — kurulum rehberinin 12 sayfası, sayfa sayfa, 24 numaralı gereksinime çevrilmiş
- [`docs/arastirma/`](docs/arastirma/) — akademik tarama. **Yöntem kısıtını önce okuyun:** tam metin erişimi engellendiği için tam okunan kaynak sayısı 0'dır.
