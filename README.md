# bist

Borsa İstanbul analiz sistemi — skill'ler, dokümantasyon ve yayın altyapısı.

Kaynak paket: Eren Gül Aydın, *Borsa İstanbul Analiz Sistemi* (Apache 2.0).
Lisans ve atıflar: [THIRD-PARTY.md](THIRD-PARTY.md).

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

## Test

```bash
python3 tools/test_zip_to_md.py
python3 tools/test_site.py
```
