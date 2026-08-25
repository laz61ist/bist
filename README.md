# bist

Markdown tabanli proje deposu. ZIP ile gelen dokumanlarin `docs/` altina cikarilip
tarayicidan okunmasi icin kucuk bir arac seti.

## Yapi

```
.claude/settings.json   Claude Code izin ayarlari (allow / deny / ask)
tools/zip_to_md.py      ZIP icinden .md dosyalarini cikarir
tools/test_zip_to_md.py Cikarici icin testler
server/app.py           docs/ altini tarayicida gosteren sunucu
docs/                   Cikarilan markdown dosyalari (repoya girer)
incoming/               Ham ZIP dosyalari (git'e girmez)
```

## ZIP -> Markdown

```bash
python3 tools/zip_to_md.py incoming/paket.zip           # docs/paket/ altina cikar
python3 tools/zip_to_md.py --all                        # incoming/ altindaki tum zipler
python3 tools/zip_to_md.py incoming/paket.zip --flat     # klasor yapisini duzlestir
python3 tools/zip_to_md.py incoming/paket.zip --dest docs/spec
python3 tools/zip_to_md.py incoming/paket.zip --commit   # cikar + commit
```

Davranis:
- Sadece `.md`, `.markdown`, `.mdown`, `.mkd` alinir; digerleri atlanir.
- Mutlak yol ve `..` iceren girisler (zip-slip) reddedilir; `__MACOSX`, `.DS_Store`, `.git` atlanir.
- Ayni isimde dosya varsa uzerine yazilmaz, `-2`, `-3` eklenir (`--overwrite` ile degistirilebilir).
- Turkce dosya adlari: UTF-8 bayragi yoksa cp437 -> utf-8 / cp1254 / cp857 sirasiyla cozulur.

## Sunucu

```bash
pip install -r server/requirements.txt
python3 server/app.py                     # http://127.0.0.1:8000
python3 server/app.py --host 0.0.0.0 --port 9000
```

`docs/` altindaki tum markdown dosyalarini soldaki listeden gezdirir; tablo, fenced code
ve baslik desteklidir, acik/koyu tema otomatiktir.

## Test

```bash
python3 tools/test_zip_to_md.py
```
