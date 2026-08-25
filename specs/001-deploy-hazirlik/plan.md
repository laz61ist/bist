# Uygulama Planı: Deploy Hazırlığı — Karardan Bağımsız Faz

**Taban:** `main` @ `2bdfa2c` · 92 test yeşil
**Kapsam:** A1/A2 kararını beklemeyen 6 madde

## Faz sırası ve gerekçe

Sıra şiddet ve bağımlılığa göre. Her madde bir öncekinin açtığı kapıyı kullanır.

| Sıra | Madde | Issue | Neden bu sırada |
|---|---|---|---|
| 1 | D9 · Girdi boyutu sınırı | #13 | **KRİTİK** ve bağımsız. Anonim DoS yüzeyini kapatır. Diğer hiçbir şeye dokunmaz. |
| 2 | D1 · Yapılandırma değişkenleri | #5 | D4'ün ihtiyaç duyduğu `BIST_ENV`'i getirir. Küçük ve izole. |
| 3 | D4 · Hata yakalama ve log | #8 | D1'in `BIST_ENV`'ini kullanır. D5'in üzerine kurulacağı altyapı. |
| 4 | D5 · Dürüst sağlık kontrolü | #9 | D4'ün istisna yakalayıcısı olmadan 503 üretemez. |
| 5 | D10 · CI ayrıcalıkları | #14 | Sadece workflow; koddan bağımsız, sona bırakıldı çünkü D7 aynı dosyaya dokunacak. |
| 6 | D7 · E2E'yi CI'a al | #11 | D10 ile aynı workflow dosyası; çakışmayı önlemek için art arda. |

## Beklenen mimari değişiklikler

### Yeni sınıflar

| Sınıf | Sorumluluk | Madde |
|---|---|---|
| `Bist\Config\Ayarlar` | Env okuma, öncelik sırası, güvenli varsayılan | D1 |
| `Bist\Http\GovdeOkuyucu` | İstek gövdesini sınırlı okuma, boyut/derinlik denetimi | D9 |
| `Bist\Http\HataYakalayici` | Global istisna yakalama, jenerik 500, log | D4 |
| `Bist\Data\SaglikDenetimi` | DB'ye ucuz sorgu, bileşen durumu | D5 |

### Değişecek sınıflar

- `Bist\App` — `kriterKaydet` doğrulaması (D9), `/saglik` gerçek denetim (D5), depo tembel (D4)
- `public/index.php` — `Ayarlar` kullanımı (D1), `GovdeOkuyucu` (D9), `HataYakalayici` (D4)

### Dokunulmayacaklar (anayasa madde VIII)

`src/Domain/*` — alan mantığı bu fazda **hiç değişmiyor**. 92 testin hepsi bu fazın
sonunda da aynı şekilde geçmeli. Geçmezse regresyon var demektir.

## TDD disiplini (anayasa madde V)

Her madde için sıra sabit:

1. Testi yaz
2. **Kırmızıyı gör** — hata (error) değil başarısızlık (failure) olmalı; hata alınırsa
   önce stub konur, gerçek başarısızlık görülür
3. Geçecek en az kodu yaz
4. **Yeşili gör** — yeni test + eski 92 test birlikte
5. Refactor, yeşil kalarak
6. Commit, issue'ya bağla

## Kabul

Faz sonunda:

```
./vendor/bin/phpunit --testsuite Unit   -> 92'den fazla test, hepsi yeşil
./tests/E2E/calistir.sh                 -> GECEN: 27+  KALAN: 0
```

Kabul kriterlerinden karşılananlar: K3, K4, K5, K6, K8, K11, K12
Beklemede kalanlar: K1, K2, K7, K9, K10 (A1/A2 kararına bağlı)
