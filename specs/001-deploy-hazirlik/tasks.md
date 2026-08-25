# Görevler: Production Deploy Hazırlığı

Efor: **S** = yarım günden az · **M** = 1-3 gün · **L** = 3 günden fazla

Sıra bağımlılıklıdır. D1 yapılmadan D2 anlamsız; D2 yapılmadan D4/D5 test edilemez.

## Deploy'u ENGELLEYEN

### D1 · Production yapılandırma değişkenleri `S`
**Sorun:** Uygulamadaki tek `getenv()` çağrısı `E2E_DB_PATH`. Production'da
veritabanı yolunu vermenin tek yolu, adı "bu bir test değişkenidir" diyen bir
değişkeni set etmek. Sonraki geliştirici için kurulmuş tuzak.

**Yapılacak:** `BIST_DB_PATH` ve `BIST_ENV` ekle. `E2E_DB_PATH` geçiş dönemi
için desteklenmeye devam etsin. `.env.example` yaz, `.env`'i gitignore'a ekle.

**Test önce:** Env okuma için birim testi — `BIST_DB_PATH` varsa o kullanılır,
yoksa `E2E_DB_PATH`, o da yoksa varsayılan.

**Bağımlılık:** yok · **Engelliyor:** D2

---

### D2 · Çalıştırma ortamı: Dockerfile + web sunucu yapılandırması `L`
**Sorun:** Depoda `Dockerfile`, `nginx.conf`, `.htaccess`, `Procfile`,
`fly.toml`, `render.yaml` — hiçbiri yok. "Deploy et" komutunun karşılığı yok.
Pages statiktir, PHP koşturamaz.

**Yapılacak:** Çok aşamalı `Dockerfile` (`composer install --no-dev
--optimize-autoloader` + çalışma katmanı), `pdo_sqlite` + `mbstring` uzantıları,
docroot `public/`, var olmayan dosya → `index.php` yönlendirmesi, `.dockerignore`.

**Karar gerekli:** A1 — barındırma hedefi (kullanıcı seçecek)

**Bağımlılık:** D1 · **Engelliyor:** D3, K1

---

### D3 · Verinin kalıcı olması `M`
**Sorun:** Sqlite `var/bist.sqlite`'ta, `var/` gitignore'da, volume tanımı yok.
İlk yeniden deploy'da kullanıcının kaydettiği kriter setleri **sessizce** yok
olur — kod yeni boş dosya yaratır, `CREATE TABLE IF NOT EXISTS` çalışır,
uygulama hiçbir hata vermez.

**Yapılacak:** DB yolunu kalıcı volume'e taşı (`/data/bist.sqlite`), volume
bağla, `PRAGMA journal_mode=WAL` + `busy_timeout` ekle, zamanlanmış yedek
(`VACUUM INTO`), instance sayısını 1'e sabitle ve README'ye yaz.

**Test önce:** Kayıt at → süreci yeniden başlat → kayıt duruyor mu.

**Bağımlılık:** D2 · **Kabul:** K2

---

### D4 · Hata yakalama ve log `M`
**Sorun:** `set_exception_handler`, `error_log`, logger — hiçbiri yok.
Üç sonuç: (a) yakalanmamış istisna 500 + olası dosya yolu ifşası,
(b) `KriterDeposu` koşulsuz kuruluyor ve `CREATE TABLE` çalıştırıyor, yani
**disk salt okunur olduğunda ana kokpit sayfası da çöküyor**,
(c) production'da bir istek 500 döndüğünde geriye bakılacak iz yok.

**Yapılacak:** Global `set_exception_handler` — kullanıcıya jenerik 500,
`error_log`'a tam ayrıntı. `display_errors=Off`, `log_errors=On` içeren
`php.ini` parçası imaja. `KriterDeposu`'nu tembel kur (lazy).

**Test önce:** Yazılamaz DB yolunda `/` 200 dönmeli, yanıtta `/home/` ve
`Stack trace` geçmemeli.

**Bağımlılık:** D1 · **Kabul:** K4, K6

---

### D5 · Dürüst sağlık kontrolü `S`
**Sorun:** `/saglik` yalnızca sabit bir dize döndürüyor, hiçbir şeye dokunmuyor.
Sqlite bozulsa, volume bağlanmasa, disk dolsa bile 200 `{"durum":"ok"}` döner.
**Var olmayan health check'ten kötüsü, yalan söyleyen health check'tir.**

**Yapılacak:** DB'ye ucuz sorgu (`SELECT 1`), başarısızsa 503.

**Test önce:** DB erişilemezken `/saglik` 503, erişilebilirken 200.

**Bağımlılık:** D4 · **Kabul:** K5

---

### D6 · Yazma ucuna erişim denetimi `M`
**Sorun:** `POST /kriter` oturum, token, CSRF ve hız sınırı olmadan sqlite'a
yazıyor. `GET /kriter` tüm kayıtları kimlik sormadan listeliyor. İnternete
açıldığı anda herkes yazar, herkesin yazdığını okur. Sınırsız yazma = disk
dolana kadar büyüyen dosya.

**Adil olmak gerekirse:** SQL enjeksiyonu yok (hazırlanmış ifade), XSS kaçışı
var, `nosniff` ve `Referrer-Policy` başlıkları var, şirket kodu regex ile
beyaz listeli. Eksik olan **erişim denetimi**.

**Karar gerekli:** A2 — yazmayı tamamen kapat / paylaşımlı parola / oturum

**Test önce:** Token'sız `POST /kriter` 401 dönmeli.

**Bağımlılık:** yok · **Kabul:** K7

---

### D7 · E2E paketini CI'da koşturulabilir yap `M`
**Sorun:** İki ayrı kusur:
1. `tests/E2E/kokpit.e2e.mjs:9` mutlak yol kullanıyor:
   `import pw from '/opt/node22/lib/node_modules/playwright/index.js'`
   Statik ESM import olduğu için env değişkeniyle geçersiz kılınamaz.
2. Depoda `package.json` yok; Playwright hiçbir yerde bağımlılık olarak
   beyan edilmemiş.

Sonuç: 27 kontrol yalnızca tek bir makinede elle çalışıyor — pratikte hiç
çalışmıyor. Oysa bu paket, anayasanın II. maddesini (tavsiye dili yasağı)
doğrulayan **tek otomatik kapı**.

**Yapılacak:** `package.json` + `package-lock.json`, mutlak import'u
`from 'playwright'` yap, `E2E_CHROME` varsayılanını kaldır, workflow'a iş ekle
(`setup-node` → `npm ci` → `npx playwright install --with-deps chromium` →
`./tests/E2E/calistir.sh`), ekran görüntülerini artifact olarak yükle.

**Bağımlılık:** yok · **Kabul:** K8

---

### D8 · Sürüm etiketleme ve geri alma `S`
**Sorun:** `git tag -l` boş. Release yok, CHANGELOG yok, kökte `LICENSE` yok
(oysa `composer.json` projeyi Apache-2.0 ilan ediyor). Bozuk sürüm çıkarsa
geri dönülecek işaretli nokta yok.

Bu oturumda dalın eski bir `origin/main`'e resetlenip uygulama dosyalarının
diskten silindiği gözlendi — sürüm disiplininin neden gerektiğinin canlı örneği.

**Yapılacak:** Anlamsal sürüm etiketi (`v0.1.0`), imajı hem commit SHA'sı hem
etiketle damgala, kökte `LICENSE`, `main` dalına branch protection.

**Bağımlılık:** D2 · **Kabul:** K9, K10

---

## Deploy'dan SONRA yapılabilecekler

| # | İş | Efor | Not |
|---|---|---|---|
| S1 | CSP ve HSTS başlıkları | M | Renderer satır içi `<style>`/`<script>` üretiyor; katı CSP için nonce/hash gerekir |
| S2 | Google Fonts'u kendi sunucundan servis et | S | Üçüncü taraf bağımlılığı + gizlilik + kapalı ağ sorunu. E2E testi zaten bu isteklerin başarısız olduğu ortamı hariç tutmak zorunda kalmış |
| S3 | Hata takip servisi | M | D4'ün üstüne gelir |
| S4 | `composer.json` PHP kısıtını `^8.3` yap | S | `>=8.3` üst sınırsız; PHP 9'da composer memnun, uygulama muhtemelen bozuk |
| S5 | `dist/` zip'lerini Release varlığına taşı | S | Üretilen artefakt kaynak kontrolünde durmamalı |
| S6 | Yedekten geri yükleme provası | S | Denemeden yedeğin olduğunu söyleyemezsin |

## Bağımlılık grafiği

```
D1 (env) ──┬─→ D2 (Dockerfile) ──┬─→ D3 (kalıcı veri)
           │                      └─→ D8 (sürüm/rollback)
           └─→ D4 (hata/log) ────────→ D5 (sağlık)

D6 (erişim denetimi)  ── bağımsız
D7 (E2E CI)           ── bağımsız
```

D6 ve D7 paralel yürütülebilir; diğerleri sıralı.
