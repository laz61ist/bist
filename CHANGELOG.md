# Değişiklik günlüğü

Biçim [Keep a Changelog](https://keepachangelog.com/tr/1.1.0/) esaslı,
sürümleme [Anlamsal Sürümleme](https://semver.org/lang/tr/) esaslıdır.

## [0.1.0] — 2026-08-25

İlk deploy edilebilir sürüm. Kokpit salt okunur olarak yayınlanabilir;
veri kaynağı henüz bağlı değil, bu kasıtlıdır (temsili veri üretilmez).

### Eklendi
- Alan mantığı: `Metrik`, `Tms29Kontrol`, `Oran`, `KriterTarama`, `EksiklikNedeni`
- `VeriKaynagi` arayüzü ve temsili veri üretmeyen `BosKaynak`
- Tek dosya HTML kokpit üreticisi (tasarım tokenleri proje talimatıyla birebir)
- sqlite kriter deposu; kullanıcının eşiği ve **kendi gerekçesi** birlikte saklanır
- `Ayarlar` / `Ortam`: `BIST_DB_PATH`, `BIST_ENV`, `BIST_YAZMA_TOKEN`, `BIST_AZAMI_GOVDE_BAYT`
- `GovdeOkuyucu`: iki kapılı istek gövdesi sınırı
- `HataYakalayici`: olay kimlikli jenerik 500, log'a tam ayrıntı
- `Yetki`: paylaşımlı token; **yapılandırılmamışsa yazma kapalı**
- Dürüst `/saglik`: DB'ye gerçek sorgu, bozuksa 503
- WAL, `busy_timeout`, `VACUUM INTO` ile yedekleme
- Production Docker imajı (Apache + mod_php, docroot `public/`, volume `/data`)
- CI: birim testleri, Playwright E2E, Docker imajı + duman testi, Pages yayını

### Güvenlik
- Girdi boyutu sınırlandı — 12 MB gövde `201` yerine `413` (#13)
- Yazma ve okuma uçları yetki ister; anonim erişim kapandı (#10)
- Yakalanmamış istisna yığın izi ve dosya yolu sızdırmıyor (#8)
- CI ayrıcalıkları daraltıldı, action'lar commit SHA'sına sabitlendi (#14)
- `PUT`/`DELETE`/`PATCH` artık 405; önce 200 + tam liste dönüyordu
- Şirket kodu regex'i sondaki satır sonunu kabul etmiyor (`\A..\z`)
- İstisna mesajı iç namespace sızdırmıyor

### Bilinen kısıtlar
- **Sqlite yatay ölçeklemeyi yasaklar.** Instance sayısı 1 olmalıdır.
- Kalıcı volume bağlanmazsa veri her yeniden başlatmada kaybolur.
- Gerçek BIST veri kaynağı bağlı değil; tüm metrikler `—` gösterir.
