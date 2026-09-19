# Kurulum ve İşletim

## Gereksinimler
- PHP 8.3+ (`pdo`, `mbstring`, `json`; SQLite için `pdo_sqlite`, MySQL için `pdo_mysql`; XLSX için `zip` isteğe bağlı)
- Composer **isteğe bağlı** (yalnızca PHPUnit için). Uygulama yerleşik PSR-4 autoloader ile composer'sız çalışır.
- Web sunucusu: Apache (`public/.htaccess` hazır) veya Nginx (`try_files $uri /index.php?$query_string;` ile docroot `public/`).

## Hızlı başlangıç (SQLite)
```bash
cd aidat
cp .env.example .env
php bin/aidat key:generate          # çıktıyı .env içindeki APP_KEY'e yazın
php bin/aidat migrate
php bin/aidat seed                  # demo veri (isteğe bağlı) — şifre Demo1234!
php bin/aidat serve                 # http://127.0.0.1:8090
```
Demo hesaplar: `demo.admin@aidat.local` (süper yönetici), `demo.yonetici@…`, `demo.muhasebe@…`, `demo.denetci@…`, `demo.gorevli@…`, `demo.malik@…`, `demo.kiraci@…`.

Gerçek kurulumda demo yerine:
```bash
php bin/aidat user:create --email=yonetici@site.com --password='GucluSifre!' --name='Ad Soyad' --role=admin
```
Giriş yaptıktan sonra "Yeni yapı" ile ilk apartmanı oluşturun; kasa hesabı ve gider kategorileri otomatik gelir.

## MySQL / MariaDB
`.env`:
```
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_NAME=aidat
DB_USER=aidat
DB_PASS=...
```
`CREATE DATABASE aidat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` sonra `php bin/aidat migrate`.

## Zamanlanmış görevler (cron)
```
0 6 1 * *   php /yol/aidat/bin/aidat dues:generate            # ayın 1'i: tekrarlı tahakkuk planları
0 6 1 * *   php /yol/aidat/bin/aidat recurring:generate       # periyodik giderler
0 7 * * *   php /yol/aidat/bin/aidat latefee:apply            # gecikme tazminatı (ayarlardan kapatılabilir)
0 9 * * 1   php /yol/aidat/bin/aidat reminders:send           # borç hatırlatma (uygulama içi + e-posta)
0 3 * * *   php /yol/aidat/bin/aidat backup                   # yedek → storage/backups
```

## E-posta ve SMS
- `MAIL_DRIVER=log` (varsayılan): iletiler `notifications` tablosuna yazılır, gönderilmez. `mail` → PHP `mail()`; `smtp` → yerleşik SMTP istemcisi (`SMTP_*`).
- `SMS_DRIVER=webhook`: `SMS_WEBHOOK_URL` adresine `{"to":"5xx…","text":"…"}` JSON POST edilir (Bearer token isteğe bağlı). Sağlayıcıya özel SDK yok; çoğu Türk SMS sağlayıcısının HTTP API'sine küçük bir ara katmanla bağlanır.

## Dosyalar ve güvenlik
- Yüklemeler `storage/uploads/` altında (web kökü dışı), yalnızca yetki denetimli `/belge/{id}` veya süreli imzalı `/dosya/{token}` ile servis edilir.
- `storage/` ve `.env` web'den erişilemez olmalı (Apache için `.htaccess` kuralı var; Nginx'te `location ~ /\.env { deny all; }`).
- Oturum çerezi HttpOnly + SameSite=Lax; HTTPS ise `Secure` (APP_URL https ile başlıyorsa).
- CSP: yalnızca kendi alan adı; harici CDN kullanılmaz.
- Tüm finansal yazımlar `audit_logs` tablosuna kim/ne/eski/yeni/ne zaman/IP ile düşer.

## Yedekleme
`php bin/aidat backup` → SQLite için `VACUUM INTO` ile tutarlı kopya, MySQL için SQL dökümü. Yönetim panelinde "Yedek ve dışa aktarım" ekranından indirilebilir; ayrıca tablo bazlı CSV dışa aktarım vardır.

## Testler
```bash
composer install            # PHPUnit
./vendor/bin/phpunit        # birim testleri (bellek içi SQLite)
tests/smoke.sh              # çalışan sunucuya karşı tüm GET rotaları
node tests/screenshot.mjs   # Playwright ekran görüntüleri (kök dizinde npm ci gerekir)
```

## Docker
```bash
docker build -t aidat -f aidat/Dockerfile aidat
docker run -d -p 8080:80 -v aidat-veri:/var/www/html/storage -e APP_KEY=$(php -r 'echo bin2hex(random_bytes(32));') aidat
```
