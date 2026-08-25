# Özellik Şartnamesi: Production Deploy Hazırlığı

**Özellik dalı:** `001-deploy-hazirlik`
**Oluşturma:** 2026-08-25
**Durum:** Taslak
**Taban:** `main` @ `3d328e4`

## Hüküm

**Uygulama bugün deploy edilemez.** Kod kalitesi ile deploy hazırlığı ayrı
şeylerdir. Alan mantığı disiplinli (92 birim testi CI'da yeşil, tip seviyesinde
kısıtlar, `composer.lock` kilitli, docroot ayrımı doğru). Sorun kodda değil,
**kodun etrafında hiçbir şey olmamasında**: uygulamanın koşacağı yer tanımlı
değil, verisi kalıcı değil, hatası görünmez, yazma ucu herkese açık.

Mevcut tek yayın hattı GitHub Pages ve o **statik** — `public/index.php`
orada çalışmaz.

## Kapsam

### Kapsam içi
- PHP uygulamasının çalıştırılabilir bir artefakta dönüştürülmesi
- Verinin deploy'lar arasında hayatta kalması
- Hatanın görünür olması, sağlık kontrolünün dürüst olmasi
- Yazma ucuna erişim denetimi
- E2E paketinin CI'da koşabilir hale gelmesi
- Sürüm etiketleme ve geri alma

### Kapsam dışı (bu özellikte yapılmayacak)
- Gerçek BIST veri kaynağı bağlama (ayrı özellik)
- Yatay ölçekleme — sqlite bunu **yasaklıyor**, tek instance kısıtı kabul edildi
- Kullanıcı yönetimi / çok kiracılık
- Fintables MCP entegrasyonu (hesap ayarı, kod dışı)

## Kabul kriterleri

Özellik şu koşullar sağlandığında tamamlanmış sayılır:

| # | Kriter | Nasıl doğrulanır |
|---|---|---|
| K1 | Uygulama tek komutla çalıştırılabilir bir imaj olarak kurulabiliyor | `docker build` başarılı, konteyner ayakta, `/` 200 dönüyor |
| K2 | Konteyner yeniden başlatıldığında kaydedilmiş kriter setleri duruyor | Kayıt at → konteyneri yeniden başlat → kayıt hâlâ listede |
| K3 | Veritabanı yolu production adlı bir env değişkeninden okunuyor | `BIST_DB_PATH` set edilerek doğrulanır; `E2E_` öneki production yolunda yok |
| K4 | Yakalanmamış istisna kullanıcıya yığın izi veya dosya yolu göstermiyor | DB yolunu yazılamaz yapıp istek at; yanıtta `/home/`, `Stack trace` geçmiyor |
| K5 | Veritabanı erişilemezken `/saglik` 503 dönüyor | DB dosyasını sil/kilitle, `/saglik` 503 |
| K6 | Veritabanı erişilemezken kokpit sayfası yine de açılıyor | Aynı koşulda `/` 200 dönüyor |
| K7 | Kimlik doğrulaması olmadan `POST /kriter` 401/403 dönüyor | Token'sız istek reddediliyor |
| K8 | E2E paketi CI'da koşuyor ve yeşil | Workflow çıktısında `GECEN: 27 KALAN: 0` |
| K9 | Sürüm etiketi var ve geri alma denenmiş | `v0.1.0` etiketi + geri alma tatbikatı notu |
| K10 | `main` dalı korumalı | Doğrudan push reddediliyor |

## Anayasa uyumu

Bu özellik anayasanın hiçbir maddesini gevşetmez. Özellikle:

- **Madde V (test önce):** Her deploy değişikliği için önce başarısız test yazılır.
  Altyapı kodunun da testi vardır: sağlık ucu, hata yakalayıcı, env okuma.
- **Madde IX (sır repoya girmez):** Token, parola ve DB yolu env'den okunur.
  `.env` gitignore'a eklenir.
- **Madde X (kanıtsız tamam denmez):** Her kabul kriteri komut çıktısıyla doğrulanır.

## Bilinen kısıtlar — yazılı hale getiriliyor

1. **Sqlite yatay ölçeklemeyi yasaklar.** Tek dosya, ağ dosya sistemlerinde
   kilitleme güvensiz. İki instance aynı dosyayı paylaşırsa bozulma riski,
   paylaşmazsa kullanıcı farklı veri görür. Instance sayısı **1** olarak
   sabitlenir ve README'ye yazılır.
2. **`php -S` production'a çıkamaz.** PHP el kitabında production dışı olarak
   işaretli, tek iş parçacıklı. `tests/E2E/AppServerRouter.php` bir test
   yönlendiricisidir, production yolu değildir.
3. **Kalıcı volume olmadan PaaS deploy'u veri kaybı garantisidir.**

## Açık kararlar

| # | Karar | Seçenekler | Karar veren |
|---|---|---|---|
| A1 | Barındırma hedefi | Kendi VPS / Docker+PaaS+volume / paylaşımlı hosting | Kullanıcı |
| A2 | Yazma ucu politikası | Tamamen kapat (salt okunur) / paylaşımlı parola / oturum yönetimi | Kullanıcı |

A1 ve A2 cevaplanmadan **B1 ve B6 tamamlanamaz**; diğer maddeler bağımsız ilerler.
