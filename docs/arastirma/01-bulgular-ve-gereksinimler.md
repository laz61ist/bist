# Tarama bulguları ve bunlardan türetilen yazılım gereksinimleri

> Yöntem kısıtı için önce [`00-YONTEM-VE-KISIT.md`](00-YONTEM-VE-KISIT.md) okuyun.
> **Tam okunan kaynak: 0.** Aşağıdaki her künye `[yalnızca özet okundu]` düzeyindedir.

Dört tarama ekseni: (A) TMS 29 ve BIST, (B) IAS 29 uluslararası,
(C) LLM finansal halüsinasyon, (D) tavsiye sınırı ve pano tasarımı.

---

## 1. Uygulanan bulgular — koda girdi ve testle sabitlendi

Bunlar bu depoda **hayata geçirildi**. Her satırın karşısındaki test dosyası
davranışı kilitliyor.

| # | Bulgu | Uygulama | Test |
|---|---|---|---|
| U1 | Ölçü birimi birinci sınıf alan olmalı; farklı birimdeki dönemler karşılaştırılmamalı | `Tms29Kontrol` dört satırlık karar tablosu; karışık durumda oran **hesaplanmaz** | `Tms29KontrolTest` |
| U2 | Düzeltme sonrası FAVÖK gibi kalemler pozitiften negatife dönebiliyor; yüzde değişim yanıltıcı olur | İşaret değişiminde yüzde üretilmez, **mutlak fark** döner | `testIsaretDegisimindeYuzdeDegisimUretilmez` |
| U3 | Boş bırakma **tipli** olmalı; tek tip "bilmiyorum" bilgi kaybıdır | `EksiklikNedeni` enum'u: 6 tip, her birinin ayrı kullanıcı eylemi | `EksiklikNedeniTest` |
| U4 | "Boşluk + açıklama" en yüksek karar güvenini veren gösterim | Boş metrik `—` + neden + eylem; gizlenmiyor | `MetrikTest`, E2E adım 3 |
| U5 | "Değerlendirilemedi" ile "kaldı" ayrı tutulmalı; birleştirmek eksik veriyi olumsuz hükme çevirir | `TaramaSonucu` üç ayrı grup döndürür | `KriterTaramaTest` |
| U6 | Sistem kendi eşiğini önerirse "uygunluk hükmü"ne yaklaşır | `KriterSeti` boş kurulamaz; eşik yalnızca kullanıcıdan gelir | `testBosKriterSetiKurulamaz` |
| U7 | Sıralanmış "en iyi N" ve niteleyici sıfatlar örtük tavsiyedir | Çıktıda yasak-ifade denetimi; özet yalnızca sayım verir | `testOzetTavsiyeIfadesiIcermez`, E2E adım 8 |
| U8 | Hesap adımının kendisi de atıflanabilir olmalı | `Oran` ve `Karsilastirma` girdi kaynaklarını birleştirip taşır | `OranTest` |
| U9 | Kullanıcı kararından hesap verebilir hissetmeli (otomasyon yanlılığı) | Kriter setiyle birlikte kullanıcının **kendi gerekçesi** sqlite'a yazılır | `KriterDeposuTest`, E2E adım 12 |
| U10 | Kalibrasyon geri bildirimi: kaç veri noktası eksik, açıkça sayılmalı | `ozet()` kaç şirketin değerlendirilemediğini sayar | `testOzetSadeceSayimVerir` |
| U11 | Payda ≤ 0 iken oran tanımsızdır; sayı uydurulmamalı | `Oran` sıfır/negatif paydada boş metrik döner | `OranTest` |

## 2. Kabul edilen ama henüz uygulanmayan bulgular

Gerekçesi güçlü, kapsamı bu turun dışında. Sıraya alındı.

| # | Bulgu | Neden şimdi değil |
|---|---|---|
| B1 | Birincil anahtar `(dönem)` değil `(dönem + ölçüm birimi tarihi + kaynak rapor)` olmalı — TMS 29 her yıl geçmişi yeniden ifade ediyor, aynı dönemin rakamı her raporda farklı | Veri kaynağı bağlanınca anlam kazanır; şu an tek kaynak `BosKaynak` |
| B2 | `ham_deger` ve `ham_olcek` **ayrı kolonlarda** tutulmalı; ölçek değere gömülmemeli | Aynı sebep |
| B3 | Tolerans sabit yüzde olamaz; kaynak dokümanın **yuvarlama biriminin** fonksiyonu olmalı | Doğrulama katmanı henüz yok |
| B4 | Banka/finans kurumları (BDDK istisnası) farklı rejimde; emsal tablosunda rejim bayrağı zorunlu | Emsal analizi modülü henüz yok |
| B5 | VUK ve TFRS iki ayrı kaynak; 2025-2027 için düzeltme rejimleri ayrışıyor, tek seride birleştirilemez | Aynı sebep |
| B6 | Kalem eşleştirmesi embedding'e bırakılamaz ("Net Kâr" ↔ "Net Satış" karışması) — kontrollü sözlük gerekli | `MetrikSozlugu` bu yönde ilk adım; tam taksonomi sonraki iş |
| B7 | Net parasal pozisyon kazanç/kaybı ayrı kalem olarak izlenmeli; piyasa bu kalemi fiyatlıyor | Veri kaynağına bağlı |

## 3. Literatürde çelişen bulgular — gizlenmedi

Bunlar tek yönlü bir tasarım kuralı çıkarmayı **engelliyor**:

1. **Düzeltme oranları bozar mı?** 2003-2004 Türkiye verisiyle yapılan çalışma
   neredeyse hiç anlamlı değişim bulmuyor; 2023-2024 çalışmaları marjlarda ve
   kârlılıkta anlamlı düşüş buluyor. Farklı enflasyon rejimi, farklı örneklem.
2. **Düzeltilmiş veri daha mı iyi?** Zimbabve verisiyle bir çalışma tarihi
   maliyetin değer ilgililiğinde **üstün** olduğunu buluyor. Türkiye verisiyle
   bir başkası ikisinin **tamamlayıcı** olduğunu söylüyor. Yani "düzeltilmiş =
   doğru" varsayımı kodlanmamalı.
3. **RAG halüsinasyonu azaltır mı?** Bir çalışma %15 azalma bildiriyor; başka
   bir benchmark'ta retrieval'lı model yine de soruların %81'inde yanlış
   cevap veya ret üretiyor. Göreli iyileşme ile mutlak hata seviyesi farklı
   şeyler.
4. **Belirsizliği göstermek iyi midir?** Bir grup çalışma güveni artırdığını,
   bir grup kesinliği düşürdüğünü, bir grup yanlış anlamaya yol açabildiğini
   söylüyor.

Bu çelişkiler, U1-U11'in neden **kural** değil **kapı** olarak tasarlandığını
açıklıyor: sistem hüküm vermiyor, hesaplamayı durdurup nedeni yazıyor.

## 4. Tespit edilen literatür boşlukları

- Düzeltilmiş ve düzeltilmemiş dönemin karıştırılmasından doğan hatanın
  **büyüklüğünü ölçen** hakemli çalışmaya rastlanmadı. Bu yüzden araç bu
  hatayı "bilinen bir katsayıyla düzeltme" iddiasında **bulunmamalı**;
  yapabileceği tek doğru şey hesabı engellemek ve uyarmaktır.
- Kullanıcının kendi eşiğini koyduğu tarama ile tavsiye arasındaki sınırı
  doğrudan ele alan hakemli çalışma bulunamadı.
- Finansal alanda "kanıt getirildi ama görmezden gelindi" oranını ölçen
  çalışma yok; mevcut rakamlar tıp ve genel QA alanından.

## 5. Erişim açılırsa öncelikli okuma listesi

Aşağıdaki künyeler **doğrulanmamıştır**; erişim açıldığında ilk teyit
edilecekler bunlardır.

**TMS 29 / karşılaştırılabilirlik**
1. Chamisa, Mangena, Pamburai & Tauringana (2018), *Review of Accounting Studies* 23(4), 1241-1273. DOI 10.1007/s11142-018-9460-4
2. Kirkulak & Balsari (2009), *The International Journal of Accounting* 44(4), 363-377
3. Barniv (1999), *Journal of International Accounting, Auditing and Taxation* 8(2), 269-287
4. Pirgaip & Uyar (2025), *Journal of Capital Markets Studies* 9(1), 79-98. DOI 10.1108/JCMS-09-2024-0059
5. De Franco, Kothari & Verdi (2011), *Journal of Accounting Research* 49(4), 895-931. DOI 10.1111/j.1475-679X.2011.00415.x

**LLM halüsinasyon / abstention**
6. FinanceBench — arXiv:2311.11944
7. AbstentionBench — arXiv:2506.09038
8. FinLFQA — arXiv:2510.06426
9. Rashkin vd. (2021), *Measuring Attribution in NLG Models* — arXiv:2112.12870
10. FinQA — arXiv:2109.00122, EMNLP 2021

**Tavsiye sınırı / pano tasarımı**
11. ESMA denetim brifingi (2023), MiFID II yatırım tavsiyesi tanımı — **madde numarası doğrulanmadı**
12. SPK III-37.1 sayılı Tebliğ — **madde aralığı doğrulanmadı, SPK metninden teyit edilmeli**
13. Parasuraman & Manzey (2010), *Human Factors*. DOI 10.1177/0018720810376055
14. *Visualization of missing data: a state-of-the-art survey* — arXiv:2410.03712

> Bu listedeki hiçbir künye tam metinden doğrulanmadı. Akademik bir çalışmada
> atıf vermeden önce her biri birincil kaynaktan teyit edilmelidir.
