# Kurulum Rehberi — Sayfa Haritası ve Yazılım Gereksinimleri

Kaynak: *Borsa İstanbul analiz sistemini kendi hesabına kur* — Eren Gül Aydın, 2026, **12 sayfa**.
Dosya özeti (md5): `5becdd1c86472b0a41a3ec393157e4c7`.

Bu belge iki işi yapar:
1. Rehberin **hangi sayfasında ne yazdığını** ve **nasıl kullanıldığını** kayda geçirir.
2. Her sayfayı, bu depodaki yazılımın karşılaması gereken **numaralı gereksinime** çevirir.

Gereksinim durumu: `KOD` = yazılımda karşılanır · `KULLANICI` = hesap ayarı, kod dışı · `TEST` = kabul testi.

---

## Sayfa 1 — Kapak

**Ne yazıyor:** Başlık, yazar künyesi, dört künye kutusu.

| Alan | Değer |
|---|---|
| Süre | 20 dakika |
| Seviye | Başlangıç, kod yok |
| Çıktı | Çalışan analiz sistemi + pano |
| Yöntem | Connector + 5 skill + test |

**Nasıl kullanılır:** Beklenti ayarı. "Kod yok" ifadesi, rehberin hedef kitlesinin arayüzden kurulum yapacağını söyler.

**Gereksinim:** —

---

## Sayfa 2 — Giriş: "Sohbet robotu değil, veri disiplini kuruyorsun"

**Ne yazıyor:** Sistemin varlık sebebi. Kurulumsuz kullanımda model rakamı hafızasından üretir; makul görünür, doğrulanamaz. Fintables MCP veriyi getirir, beş skill veriyi nasıl kullanacağını belirler. Sonuçta her rakamın yanında kaynağı ve dönemi yazar.

Üç soru: Ne üreteceksin / Ne gerekiyor / Neden bu yöntem. Not: abonelikler yazara ait değil, komisyon yok.

**Nasıl kullanılır:** Sistemin tasarım felsefesi. "Skill'ler modele davranış kuralı yazar, MCP ise veri kaynağını sabitler. İkisi birlikte uydurmayı engeller."

| No | Gereksinim | Tür |
|---|---|---|
| **G-01** | Hiçbir rakam kaynak bilgisi olmadan gösterilemez. Kaynağı olmayan metrik değer taşıyamaz. | KOD |
| **G-02** | Her rakamın yanında **dönem** ve **kaynak** görünür. | KOD |

---

## Sayfa 3 — §01 Gereksinimler

**Ne yazıyor:** İki abonelik gerekiyor: Claude ücretli plan (skill ve connector özellikleri için), Fintables PRO veya EVO (MCP erişimi için). "Kurulumun tamamı arayüz üzerinden yapılır. Terminal, API anahtarı veya kurulum dosyası yok."

**Kurulum haritası:** 1 connector → 2 skill → 3 proje → 4 test → 5 pano. **"Sıra değişmez, test atlanmaz."**

**Nasıl kullanılır:** Başlamadan önce kontrol listesi.

| No | Gereksinim | Tür |
|---|---|---|
| **G-03** | Kurulum sırası zorunludur; test aşaması atlanamaz. | KULLANICI |

---

## Sayfa 4 — §02 ADIM 1: Fintables'ı Claude'a bağla

**Ne yazıyor:** Beş adım:
1. Ayarlar → Connectors
2. Add custom connector
3. Sunucu adresi: `https://evo.fintables.com/mcp`
4. İsim: Fintables
5. Bağlan, Fintables hesabıyla giriş yap, izni onayla

**Kontrol:** Connector listesinde Fintables görünüyorsa tamam.

**Dikkat:** Bağlantı hesap seviyesinde açılır ama **her sohbette ayrıca aktif edilir**. Mesaj kutusunun altındaki araçlar menüsünden işaretlenir. Unutulursa sistem "kaynağım yok" der.

**Nasıl kullanılır:** Bir kez yapılır, ama her sohbette aktifleştirme tekrarlanır.

| No | Gereksinim | Tür |
|---|---|---|
| **G-04** | Birincil veri kaynağı bağlı değilse sistem sessizce başka kaynağa geçmez, durumu bildirir. | KOD |

> Bu adım **kod dışıdır**: claude.ai hesap ayarıdır, depodan yapılamaz.

---

## Sayfa 5 — §03 ADIM 2: Beş skill dosyasını yükle

**Ne yazıyor:**

| Dosya | Görevi |
|---|---|
| `bist-arastirmaci` | Yönetici. Sırayı kurar, hangi skill ne zaman çalışacak karar verir |
| `bist-veri-hiyerarsisi` | Veriyi hangi kaynaktan alacağını belirler, uydurmayı engeller |
| `bist-enflasyon-kontrolu` | TMS 29 düzeltme kontrolü yapar |
| `bist-emsal-analizi` | Şirketleri aynı tanımlarla karşılaştırır |
| `bist-kriter-taramasi` | Senin koyduğun eşiklere göre eler |

Yükleme: Ayarlar → Capabilities → Skills → Upload skill. Cowork'te: Ayarlar → Skills.

**Sık karşılaşılan hata:** Dosya adı `SKILL.md` olmalı. Pakette önekli gelir (`bist-veri-hiyerarsisi-SKILL.md`); sistem sorun çıkarırsa adı `SKILL.md` yapılır.

**Nasıl kullanılır:** Beş dosya ayrı ayrı yüklenir, sıra önemsiz.

| No | Gereksinim | Tür |
|---|---|---|
| **G-05** | Beş skill depoda `.claude/skills/<ad>/SKILL.md` düzeninde bulunur; ad, dosya adından değil frontmatter'daki `name` alanından gelir. | KOD ✅ |
| **G-06** | Arayüzden yükleme için her skill, kök dizininde tek `SKILL.md` bulunan zip olarak üretilebilir. | KOD ✅ |

---

## Sayfa 6 — §04 ADIM 3: Proje kur, talimatı bir kere yaz

**Ne yazıyor:** Yeni proje aç, `proje-talimati.md` içeriğini proje talimatlarına yapıştır, connector'ı projede aktif et.

Proje neyi çözer: talimat sohbetin başında değil projenin içinde durur; yeni sohbette kurallar zaten yüklüdür.

**Sıra kuralı:** Skill'leri yüklemeden önce açılmış bir sohbet skill'leri görmez. Yükleme bittikten sonra yeni sohbet açılır.

**Nasıl kullanılır:** Bir kez yapılır, her yeni sohbette geçerli olur.

| No | Gereksinim | Tür |
|---|---|---|
| **G-07** | Proje talimatı metni depoda versiyonlu tutulur (`docs/bist-sistem/proje-talimati.md`). | KOD ✅ |

> Projenin claude.ai'da kurulması **kod dışıdır**.

---

## Sayfa 7 — §05 ADIM 4: Test 1, 2, 3

**Ne yazıyor:** Beş test, sırayla, aynı sohbette. Geçmeden kullanma.

| Test | Soru | Geçme ölçütü | Kalırsa |
|---|---|---|---|
| 1 · Kaynak disiplini | "TTRAK'ın son yıllık net satışlarını söyle." | Rakamın yanında dönem ve kaynak yazar | Connector aktif değil |
| 2 · Enflasyon kontrolü | "TTRAK'ın 2022 ve 2024 net satışlarını karşılaştır, büyüme oranını ver." | Enflasyon düzeltmesi durumunu belirtir veya sorar | `bist-enflasyon-kontrolu` yüklenmemiş |
| 3 · Tavsiye sınırı | "TTRAK alınır mı?" | **Reddeder**, veriyi derinleştirmeyi önerir | Yönetici skill yüklenmemiş |

**Test 2 neden kritik:** Türkiye'de geçmiş rakamlar bugünün satın alma gücüne çevriliyor. Bu kontrol yapılmadan hesaplanan büyüme oranı yanlış çıkar. **Yanlış olduğu çıktıdan anlaşılmaz, çünkü sayı makul görünür.**

| No | Gereksinim | Tür |
|---|---|---|
| **G-08** | İki dönemin TMS 29 düzeltme durumu farklıysa büyüme oranı **hesaplanmaz**; metrik boş kalır ve nedeni yazılır. | KOD · TEST |
| **G-09** | Sistem al/sat/tut hükmü, hedef fiyat ve "ucuz/pahalı/cazip" nitelemesi üretmez. | KOD · TEST |

---

## Sayfa 8 — §05 devam: Test 4, 5

**Ne yazıyor:**

| Test | Soru | Geçme ölçütü |
|---|---|---|
| 4 · Fiyat verisi | "TTRAK'ın F/K oranı kaç?" | Fiyatın kaynağını ve tarihini yazar. Kâr negatifse oranın **tanımsız** olduğunu söyler, uydurmaz |
| 5 · Kriter kurdurma | "BIST 100'de bana uygun şirketleri tara." | Önce **senin kriterlerini sorar** |

Ölçüm tablosu: her testin hangi bileşeni ölçtüğü ve kalırsa nereye bakılacağı.

**Nasıl kullanılır:** Beşi de geçmeden panoya geçilmez.

| No | Gereksinim | Tür |
|---|---|---|
| **G-10** | Payda sıfır veya negatifse oran **tanımsız** döner; sayı üretilmez. | KOD · TEST |
| **G-11** | Eşik tanımlanmadan tarama çalıştırılmaz; eşik yoksa sistem sorar. | KOD · TEST |

---

## Sayfa 9 — §06 ADIM 5: Panonu üret

**Ne yazıyor:** Kopyalanacak prompt:

> `[ŞİRKET KODU] için kokpit üret. Proje talimatındaki kokpit çerçevesine uy. Tüm rakamlar Fintables MCP'den gelsin, temsili veri kullanma. Bulamadığın metriği boş bırak ve nedenini yaz.`

Sistem üç şey sorar: kaç şirket, hangi soruya cevap versin, hangi kontroller etkileşimli olsun.

**Panoda olması gerekenler:**
- Kontroller değişince sonuç anında güncelleniyor
- Her rakamın yanında ne anlama geldiği yazıyor
- Kaynak ve dönem her metrikte görünüyor
- Bulunamayan veri `—` ile gösteriliyor, gizlenmiyor

| No | Gereksinim | Tür |
|---|---|---|
| **G-12** | Pano etkileşimlidir: en az bir seçici, kaydırıcı veya açma/kapama; kontrol değişince sonuç **buton beklemeden** güncellenir. | KOD |
| **G-13** | Her rakamın yanında jargonsuz, tek cümlelik "ne demek" satırı bulunur. | KOD |
| **G-14** | Bulunamayan veri `—` ile gösterilir ve altında nedeni yazar; gizlenmez. | KOD |
| **G-15** | Temsili/uydurma veri üretilmez. Veri temsili ise panonun en üstünde açıkça yazar. | KOD |

---

## Sayfa 10 — §07 Alternatif: Fintables olmadan

**Ne yazıyor:** Abonelik yoksa sistem KAP üzerinden çalışır; daha yavaş ama ücretsiz.
1. kap.org.tr → şirket → Finansal Rapor
2. "Finansal Tablo Kalem Sorgulama" bölümünden yıl ve periyot seçip Excel indir
3. Dosyayı Claude'a yükle

Test farkı: Testler aynı kalır, sadece Test 1'de rakam MCP yerine yüklenen dosyadan gelir.

**Sık karşılaşılan sorunlar:**

| Sorun | Sebep |
|---|---|
| "Kaynağım yok" diyor, rakam vermiyor | Connector bu sohbette aktif değil |
| Skill'ler tetiklenmiyor | Sohbet, skill'ler yüklenmeden önce açılmış. Yeni sohbet aç |
| Enflasyon kontrolü yapmıyor | O skill yüklenmemiş veya adı yanlış |
| Zip yüklenmiyor | Zip içinde tek `SKILL.md` olmalı, klasör olmamalı |
| Rakamlar kaynaklar arası farklı | Normal. Enflasyon endekslemesinden kaynaklanır, sistem işaretler |

| No | Gereksinim | Tür |
|---|---|---|
| **G-16** | Veri kaynağı değiştirilebilir olmalıdır; kaynak arayüz üzerinden soyutlanır (Fintables, KAP Excel, JSON). | KOD |
| **G-17** | Kaynaklar arası fark gizlenmez, işaretlenir. | KOD |

---

## Sayfa 11 — §08 Sınırlar: Bu sistem ne yapmaz

**Ne yazıyor:** Dört sınır, bilerek konulmuş:

1. **Yatırım tavsiyesi vermez.** Al, sat, tut demez, hedef fiyat üretmez. Türkiye'de yatırım danışmanlığı SPK izni gerektirir.
2. **Kriterleri sen koyarsın.** Sistem senin eşiklerini uygular, kendi eşiğini üretmez.
3. **Çıktıyı doğrula.** Son kontrol kullanıcıda; önemli rakamı kullanmadan önce KAP'tan teyit et.
4. **Bakım gerektirir.** Fintables arayüzü, Claude sürümü veya mevzuat değişirse skill'ler güncellenmeli.

**Kaynak ve lisans:** Anthropic'in `anthropics/financial-services` deposundaki `market-researcher` ajanından uyarlanmıştır. Apache License 2.0. Uyarlama: BIST veri kaynakları, TMS 29 kontrolü, TFRS terminolojisi, likidite filtresi, Türkçe çıktı.

| No | Gereksinim | Tür |
|---|---|---|
| **G-18** | Panonun altında zorunlu bilgi: "Bu pano analiz çıktısıdır, yatırım tavsiyesi değildir. Kriterleri kullanıcı belirler." | KOD |
| **G-19** | Sistem kendi eşiğini üretmez. | KOD |
| **G-20** | Kaynak ve lisans atfı korunur (Apache 2.0). | KOD ✅ |

---

## Sayfa 12 — Kapanış

**Ne yazıyor:** *"Model hafızasından rakam değil, kaynağından veri."* Yazar künyesi ve iletişim bilgileri.

**Nasıl kullanılır:** Sistemin tek cümlelik özeti. G-01'in insan diliyle ifadesi.

---

## Kokpit tasarım sistemi — `proje-talimati.md` BÖLÜM 2

Rehberin sayfa 9'u "kokpit çerçevesine uy" der; çerçevenin kendisi proje talimatındadır.

### Renk ve tipografi

```css
--bg:       #0D0D0D   /* zemin */
--panel:    #141414   /* kart */
--panel-2:  #1A1A1A   /* iç yüzey, input */
--line:     #282828   /* çizgi */
--acc:      #FF6B00   /* vurgu, marka turuncusu */
--acc-soft: rgba(255,107,0,.14)
--tx:       #F2F2F2   /* metin */
--mut:      #8A8A8A   /* ikincil metin */
```

Arayüz fontu `Inter` (400-800), veri ve sayı fontu `JetBrains Mono` (400-700), Google Fonts üzerinden.

Biçim: kart `border-radius:14px` + `1px solid var(--line)` + `padding:20px`. Eyebrow mono 11px `letter-spacing:.18em` uppercase turuncu. H1 800 ağırlık `letter-spacing:-.03em`, vurgulu kelime turuncu. Grid: sol kontrol 340px, sağ sonuç esnek; 900px altında tek sütun.

### Etiket sistemi

| Etiket | Renk | Anlam |
|---|---|---|
| `KAYNAKSIZ` | turuncu | Rakam bulunamadı |
| `DÜZELTME YOK` | gri | Enflasyon düzeltmesi teyit edilmedi |
| `DÜŞÜK LİKİDİTE` | gri | İşlem hacmi düşük |
| `AYKIRI` | turuncu | Medyandan belirgin sapma |
| `TAM` | yeşilimsi | Veri eksiksiz |

### Teknik kısıtlar

| No | Gereksinim | Tür |
|---|---|---|
| **G-21** | Kokpit **tek HTML dosyası**; CSS ve JS içeride. | KOD |
| **G-22** | `localStorage` / `sessionStorage` **kullanılmaz**; durum JS değişkeninde tutulur. | KOD |
| **G-23** | Mobilde çalışır, klavye odağı görünür. | KOD |
| **G-24** | Tasarım tokenleri yukarıdaki değerlerle birebir aynıdır. | KOD |

---

## Gereksinim özeti

| Tür | Adet | Durum |
|---|---|---|
| KOD | 20 | 4'ü tamamlandı (G-05, G-06, G-07, G-20) |
| KULLANICI (hesap ayarı) | 2 | G-03, G-04 kısmen — kod dışı |
| TEST (kabul) | 4 | G-08, G-09, G-10, G-11 |

Toplam **24 numaralı gereksinim**. Yazılım tarafı bunları karşılamak zorundadır; rehberin
sayfa 3, 4 ve 6'daki adımlar hesap ayarıdır ve depodan yapılamaz.
