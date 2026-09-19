# Aidat Yönetim Sistemi — Araştırma Özeti ve Ürün Spesifikasyonu

Tarih: 19 Eylül 2026 · Durum: uygulandı (bkz. `../app`) · Ayrıntılı kaynaklar: `ARASTIRMA-URUNLER.md` (ürünler, raporlar, makbuz, KMK), `ARASTIRMA-UI.md` (tasarım kütüphaneleri), `TASARIM.md`, `GELISTIRME-REHBERI.md`, `KURULUM.md`.

## 0. Yöntem ve dürüstlük notu
- Kaynak evreni bu talepte kullanıcı tarafından belirlendi: Türk aidat/site yönetim ürünleri (Apsiyon, Apsis, Aidatım, AidatPro, Konsiyon, SiteBYS, eyon, YÖNETİCİ, Apartmanım, Play/App Store uygulamaları), uluslararası eşdeğerler (Buildium, Condo Control, TownSq), açık kaynak GitHub projeleri (open-condo, KondoManager, StrataHQ) ve mevzuat.
- Bu oturumun ağ çıkışı ürün sitelerini ve mevzuat.gov.tr'yi engelledi; ürün bulguları arama motoru özetlerine, mevzuat bulguları hukuk sitesi alıntılarına dayanır. Her madde kaynağıyla `ARASTIRMA-URUNLER.md` içinde verildi; doğrulanamayanlar `[DOĞRULANMASI GEREKİYOR]` ile işaretlendi. Uydurma özellik veya madde numarası yoktur.
- Kullanıcının ilettiği "Full Kapsam Uygulama Planı" (menüler, formlar, droplistler, finansal doğruluk kuralları, tasarım yönü) spec olarak aynen alındı; yalnızca teknoloji kararı değişti (aşağıda §10).

## 1. Ürünlerin ortak çekirdeği (araştırmadan çıkan standart)
Tüm yerel ürünlerde ortak: bağımsız bölüm/cari hesap · otomatik aidat dağıtımı (eşit, m², arsa payı, grup) · gecikme tazminatı · kasa-banka · gelir-gider · bütçe/işletme projesi · raporlar · duyuru/iletişim · sakin ekranı. Apsiyon ayrıca sayaç okuma ile yakıt dağıtımı, malik-kiracı ayrımı, banka mutabakatı; Senyonet bütçe, sözleşme, satın alma, iş takibi; Konsiyon/SiteApp otomatik dijital makbuz ve QR; açık kaynak open-condo ödeme/fatura + sakin + mülk + talep tek mimaride. Bu ürünün kapsamı bu birleşimdir; **kredi kartı / sanal POS yoktur** (nakit, havale/EFT, çek, mahsup; banka ekstresi CSV eşleştirme vardır).

## 2. Roller ve yetki modeli
| Rol | Kapsam |
|---|---|
| Süper yönetici | Tüm yapılar, kullanıcılar, dönem kapama/açma, ayarlar, yedek, geri alma |
| Yönetici | Yapı, sakin, tahakkuk, tahsilat, gelir-gider, rapor, duyuru |
| Muhasebe sorumlusu | Finansal kayıt ve rapor; kullanıcı/yetki ve yapı ayarı yönetemez |
| Denetçi | Tüm finans ve belgelerde salt okunur; işlem izi ve dönem raporu; dışa aktarım |
| Görevli | Atanan talepler, sayaç okumaları, izin verilen gider girişi |
| Malik | Kendi bölümleri, borç/alacak, makbuzlar, gider belgeleri, duyurular, toplantılar, anketler |
| Kiracı | Sorumlu olduğu dönemler, makbuzlar, duyurular, talepler |

Yetkiler `config/permissions.php` içinde 45 anahtar (modül.eylem); rol ön tanımları vardır, kullanıcı bazında yapı içinde özelleştirilebilir (`building_users.permissions`). Ekran gizleme güvenlik sayılmaz: her rota `can:` ara katmanı ve servis düzeyinde yapı izolasyonu ile korunur.

## 3. Ana menü ve ekran içerikleri (yönetim alanı)
1. **Genel bakış** — dönem seçici; tahsilat oranı; dönem tahakkuku/tahsilatı/gideri; vadesi geçmiş borç ve borçlu bölüm sayısı; kasa+banka; kritik uyarılar (borçlu, bekleyen plan, eşleşmemiş banka hareketi, açık talep, biten sözleşme); 12 aylık tahakkuk-tahsilat-gider grafiği; blok bazlı durum; son tahsilat/gider; en yüksek borçlular; yaklaşan ödemeler; gider dağılımı; öncelikli işler ve duyurular.
2. **Yapı yönetimi** — Yapılar (çoklu yapı, yapı seçici) · Bloklar ve aidat grupları · Bağımsız bölümler (liste/filtre, tekil ve **toplu oluşturma sihirbazı**, detay: borç/avans/vadesi geçmiş, açık borçlar, tahsilatlar, sakinler, araçlar, sayaçlar, talepler, devirler, işlem geçmişi, **hesap ekstresi** yazdırılabilir) · Kişiler (gerçek/tüzel, TCKN/VKN maskeli, KVKK iletişim izni, acil durum kişisi, sakin hesabı oluşturma) · Devir ve oturum geçmişi (oturum kayıtları, **devir tutanağı**: eski/yeni taraf, bakiye kalır/devreder, depozito).
3. **Aidat ve borçlar** — Tahakkuk planları (taslak → onaylandı → işlendi/iptal; tekrar: tek/aylık/üç aylık/yıllık; **önizleme tablosu** dahil/hariç bölüm, pay, tutar, yuvarlama farkı) · Borç kayıtları (filtre, toplamlar, iptal = ters kayıt + gerekçe) · Tekil borçlandırma · Gecikme tazminatı (kural özeti, önizleme, uygulama) · Borçlu listesi (yaşlandırma 30/60/90/90+, hatırlatma gönder, CSV/XLSX) · Avans ve mahsup.
4. **Tahsilat** — Tahsilatlar (filtre, toplam, dışa aktarım) · Tahsilat girişi (bölüm seçince açık borçlar; dağıtım: **en eski borçtan / manuel / avansa al**; kısmi ve fazla ödeme önizlemesi) · Banka ekstresi içe aktar (CSV; kapı no ve ad soyad ile bölüm önerisi; **eşleştirme kuyruğu**) · Makbuzlar (makbuz defteri, seri-sıra, atlama tespiti) · Makbuz yazdır (A5, 2 nüsha, yazıyla tutar, QR doğrulama) · İptal/iade (gerekçe zorunlu, numara korunur).
5. **Gelir–gider** — Giderler (KDV dahil/hariç/yok, belge türü, kapsam tümü/blok, bütçe kalemi, ödeme durumu planlandı/kısmi/ödendi/gecikti/iptal, dosya eki) · Diğer gelirler · Kasa ve banka hesapları (bakiye, hesap ekstresi = kasa/banka defteri) · Virman/transfer (çift taraflı atomik hareket, masraf) · Periyodik giderler (kapıcı maaşı, asansör bakımı…; dönem üretimi) · Tedarikçiler · Sözleşmeler (vade uyarısı) · Gider/gelir kategorileri · **Bütçe / işletme projesi** (KMK md. 37: yıllık gelir-gider kalemleri, rezerv fonu, önerilen aylık aidat, onay/karar no, revizyon sürümü, planlanan–gerçekleşen, yazdırılabilir belge).
6. **Sayaçlar** — sayaç tanımı (su/elektrik/doğalgaz/ısı/sıcak su), dönem okumaları (tekil/toplu), **tüketim dağıtımı** → sabit pay + tüketim oranı ile tahakkuk planı.
7. **İş ve talep** — talep kategorileri (arıza/temizlik/güvenlik/şikayet/öneri/diğer), öncelik, atama, hedef tarih, görünürlük, durum akışı (yeni → inceleniyor → atandı → devam ediyor → beklemede → tamamlandı/iptal), yorumlar (iç/dış), masraf → gidere aktarım, fotoğraf eki. Personel ve demirbaş kayıtları.
8. **İletişim** — Duyurular (hedef: tümü/blok/bölüm/rol/kişi; kanal: uygulama/e-posta/SMS; okundu bilgisi) · Toplantılar ve **karar defteri** (gündem, tutanak, yeter sayı, kararlar ve oylar, katılım matrisi, yazdırılabilir karar sayfası) · Anketler/oylama · Bildirimler (gönderim geçmişi, şablonlar, borç hatırlatma) · Belgeler (kategori, görünürlük yönetim/sakinler/malikler, özel depolama, süreli bağlantı).
9. **Raporlar** — bkz. §6.
10. **Kullanıcılar ve yetkiler** — kullanıcı ekleme/davet, rol, yetki matrisi, şifre sıfırlama, yapıdan çıkarma.
11. **Ayarlar** — genel (vade günü, mali yıl), gecikme kuralı, makbuz numaralama/şablon, şeffaflık (sakin ne görür), bildirim sürücüleri; Dönemler (aylık kapama/açma, iz bırakır); Yedek ve dışa aktarım; İşlem izi.
12. **Sakin alanı** — özet (güncel borç, vade, IBAN + açıklama önerisi), borçlarım, ödemelerim ve makbuzlar (QR'lı), hesap ekstrem, yapının mali durumu (gelir-gider grafiği, kasa bakiyesi, tahsilat oranı), giderler ve belgeler, bütçe, borçlu listesi (ayara bağlı), duyurular, toplantı ve kararlar, anketler, taleplerim, belgeler, sayaçlarım.

## 4. Formlar: alanlar ve seçim listeleri
Tüm droplist değerleri tek kaynaktan gelir: `config/lists.php` (anahtarlar İngilizce/DB, etiketler Türkçe/UI). Özet:

| Form | Alanlar | Droplistler |
|---|---|---|
| Yapı | ad, tür, vergi no/dairesi, adres, il/ilçe, yönetim başlangıcı, banka/IBAN/hesap sahibi, telefon, e-posta, aktif | tür: apartman / site / rezidans / iş hanı / karma |
| Blok | ad, kod, kat sayısı, açıklama | — |
| Bağımsız bölüm | blok, kapı no, kat, tür, brüt/net m², arsa payı, aidat grubu, durum, borç sorumluluğu, gecikme muafiyeti, notlar | tür: daire/dükkan/ofis/depo/otopark/diğer · durum: dolu/boş/tadilatta/pasif · sorumluluk: malik öder/kiracı öder/paylaşımlı |
| Toplu bölüm | blok, ön ek, başlangıç–bitiş no, kat başına bölüm, ilk kat, tür, ortak m²/arsa payı | — |
| Kişi | tür, ad/soyad veya unvan, TCKN/VKN, telefonlar, e-posta, KVKK iletişim izni, acil durum kişisi, not; bölüme bağla (sıfat, sorumluluk, başlangıç) | tür: gerçek/tüzel · sıfat: malik/kiracı/oturan/vekil |
| Oturum / devir | bölüm, kişi, sıfat, sorumluluk, başlangıç–bitiş, tebligat kişisi; devir: eski/yeni taraf, tarih, bakiye kalır/devreder, depozito, tutanak | — |
| Tahakkuk planı | ad, borç türü, blok, aidat grubu, dönem, son ödeme, tekrar, bitiş dönemi, dağıtım, toplam/birim/grup tutarları, sorumluluk, KDV, açıklama; önizleme: dahil/hariç, pay, tutar, yuvarlama farkı | borç türü: aidat/yakıt/demirbaş/özel gider/sayaç/gecikme/devir/diğer · tekrar: tek/aylık/üç aylık/yıllık · dağıtım: eşit/m²/arsa payı/sabit/grup/manuel · durum: taslak/onaylandı/işlendi/iptal |
| Tekil borç | bölüm, tür, başlık, dönem, vade, tutar, sorumluluk, açıklama, gecikme muafiyeti | (yukarıdaki) |
| Tahsilat | bölüm, ödeyen, tarih-saat, kasa/banka, yöntem, tutar, referans/dekont, açıklama, dağıtım modu, manuel dağıtım | yöntem: nakit/havale/EFT/çek/mahsup/diğer · dağıtım: en eski borçtan/manuel/avansa al · durum: geçerli/iptal/iade |
| Gider | tarih, vade, dönem, kategori (2 seviye), tedarikçi, sözleşme, kasa/banka, tutar, KDV modu/oranı, belge türü/no, açıklama, kapsam, bütçe kalemi, durum, dosya | KDV: dahil/hariç/yok · belge: fatura/fiş/makbuz/sözleşme/dekont/diğer · durum: planlandı/kısmi/ödendi/gecikti/iptal · kapsam: tümü/blok |
| Transfer | tür, kaynak/hedef hesap, tarih, tutar, masraf, referans | tür: virman / kasadan bankaya / bankadan kasaya |
| Gecikme kuralı | etkin, oran türü, oran, tolerans günü, başlangıç kuralı, ayın günü, üst sınır %, bileşik | oran: aylık sabit / günlük · başlangıç: vade ertesi / ayın belirli günü |
| Bütçe | mali yıl, sürüm, başlık, dağıtım, rezerv fonu (tutar/%), kalemler (gider/gelir, kategori, yıllık tutar, aylık plan), karar no, onay tarihi | durum: taslak/onaylı/revizyonda/kapalı |
| Sayaç | bölüm, tür, seri no, çarpan; okuma: dönem, tarih, değer; dağıtım: tür, dönem, toplam fatura, sabit pay %, vade | tür: su/elektrik/doğalgaz/ısı/sıcak su |
| Talep | kategori, bölüm, kişi, başlık, açıklama, konum, öncelik, atanan, hedef tarih, görünürlük, fotoğraf | kategori: arıza/temizlik/güvenlik/şikayet/öneri/diğer · öncelik: düşük/normal/yüksek/acil · durum: yeni/inceleniyor/atandı/devam ediyor/beklemede/tamamlandı/iptal |
| Duyuru | başlık, içerik, hedef, hedef değeri, kanal, öncelik, yayın/son tarih, sabitle, ek | hedef: tümü/blok/bölüm/rol/kişi · kanal: uygulama / +e-posta / +SMS · öncelik: normal/önemli/acil |
| Toplantı | tür, başlık, tarih, yer, gündem, tutanak, yeter sayı, durum, ek; karar no, metin, oylar; katılım | tür: olağan/olağanüstü/yönetim kurulu/denetim · durum: planlandı/yapıldı/ertelendi/iptal |
| Belge | başlık, kategori, görünürlük, dosya (JPG/PNG/PDF ≤5 MB) | kategori: yönetim planı/karar defteri/işletme projesi/sözleşme/fatura/sigorta/tutanak/denetim/diğer · görünürlük: yönetim/sakinler/malikler |
| Kullanıcı | ad, e-posta, telefon, rol, şifre, yetki matrisi | rol: yönetici/muhasebe/denetçi/görevli (+süper yönetici) |
| Şeffaflık | borçlu listesi görünürlüğü, giderler, gider belgeleri, kasa bakiyesi, bütçe | gizli / kapı no / isim |

Varsayılan gider kategori ağacı (8 ana, 40 alt): Personel · Enerji ve su · Bakım ve onarım · Temizlik ve bahçe · Sigorta ve hukuk · Yönetim giderleri · Demirbaş ve yatırım · Diğer. Gelir kategorileri: aidat, gecikme tazminatı, demirbaş katkı payı, ortak alan kira, reklam/baz istasyonu, faiz, bağış, devir bakiyesi, diğer.

## 5. Finansal doğruluk kuralları (uygulanan)
- Para tam sayı **kuruş**; kayan nokta yok. Türkçe girdi `1.250,50` ve İngilizce `1,250.50` belirleyici kurallarla çözülür (birim testli).
- Finansal kayıt silinmez: tahsilat/gider/gelir/virman iptali = durum `iptal` + gerekçe + defterde **ters kayıt**; makbuz numarası korunur. Ödenmiş borç iptal edilemez.
- Makbuz numarası yapı + mali yıl içinde benzersiz ve sıralı (`CNR-2026-000123`).
- Tahsilat dağıtımı tutarı aşamaz; kalan **avans** olur ve yeni borçta otomatik mahsup edilir.
- Dağıtımda yuvarlama farkı son satıra eklenir; toplam korunur. Aynı plan aynı döneme iki kez işlenemez.
- Kapalı döneme kayıt yapılamaz; yeniden açma iz bırakır.
- Her işlem yapı sınırında; başka yapının kaydı 404. Dosyalar web kökü dışında; yetki denetimli veya süreli imzalı bağlantı.
- Her yazma `audit_logs`: kim, ne, eski/yeni değer, ne zaman, IP.

## 6. Raporlar (22)
Dönem özeti · Tahakkuk–tahsilat · Borçlu/yaşlandırma · Bölüm cari ekstresi · Kişi ekstresi · Kasa defteri · Günlük hareket · Gelir–gider · Kategori dağılımı · Nakit akışı · Bütçe planlanan–gerçekleşen · Rezerv fonu · Tedarikçi · Sözleşme vade · Sayaç tüketim · Malik–kiracı sorumluluk · Devir bakiyesi · Avans/mahsup · İptal/iade · Tahsilat yöntemi · Sakin listesi · Denetim izi. Filtreler: tarih aralığı, yıl, dönem, yapı/blok/bölüm, kişi, hesap, kategori, durum. Çıktı: ekran, yazdır/PDF (tarayıcı), CSV (UTF-8 BOM, `;`), XLSX (ZipArchive varsa). Yazdırılabilir belgeler: makbuz, hesap ekstresi, kasa/banka defteri, işletme projesi, karar defteri sayfası, devir tutanağı.

## 7. Makbuz
Araştırma (matbu "Apartman Gelir Gider Makbuzu" A5, 1 asıl + 1 kopya, seri numaralı; yazılımlarda otomatik numara, QR, e-posta) → uygulama: A5 sayfa, üstte yapı adı/adres/vergi no/IBAN, sağ üstte **seri-sıra no** ve tarih; ödeyen ve blok-kapı; tutar rakamla (mono) ve **yazıyla** ("iki bin beş yüz Türk Lirası"); ödeme şekli ve referans; **dağıtım dökümü** (dönem/kalem/tutar); ödeme sonrası kalan borç ve avans; tahsil eden unvanı ve imza alanı; alt bilgi; **QR** ile kişisel veri içermeyen doğrulama sayfası (`/makbuz-dogrula/{kod}`); nüsha sayısı ayarlanabilir (Yönetim/Sakin nüshası, kesim çizgisi); iptal/iade edilmişse mühür.

## 8. Gelir–gider tasarımı
Gelirler: aidat tahsilatı (borca dağıtılan), diğer gelirler (kira, reklam, faiz, bağış). Giderler: kategori ağacı, tedarikçi, sözleşme, KDV ayrımı, belge eki, kısmi ödeme, periyodik üretim. Kasa/banka: tüm hareketler `ledger_entries` defterinde; bakiye açılış + giriş − çıkış; hesap ekstresi ve günlük hareket raporları buradan. Bütçe: KMK md. 37 işletme projesi; planlanan–gerçekleşen kategori bazında; önerilen aylık aidat = (gider − diğer gelir + rezerv) / 12 / bölüm.

## 9. Yasal çerçeve (özet; ayrıntı ve kaynaklar `ARASTIRMA-URUNLER.md` §5)
- **KMK md. 20**: giderlere katılım (kapıcı vb. eşit; sigorta, bakım, yönetici ücreti arsa payı oranında); ödemeyen kat maliki **aylık %5 gecikme tazminatı** (5711 s. Kanun ile %10→%5). Uygulama: dağıtım seçenekleri eşit/arsa payı/m²; gecikme oranı varsayılan %5/ay, yönetim planına göre ayarlanabilir.
- **md. 28** yönetim planı bağlayıcı → oran/dağıtım/toplantı zamanı parametrik. **md. 29–32** kurul toplantısı, yeter sayı, oy hakkı, noter tasdikli **karar defteri** → toplantı/karar modülü, yazdırılabilir karar sayfası. **md. 35–36** yöneticinin görevleri, avans toplama, banka hesabı, **giderlerin belgeleriyle saklanması** → belge eki zorunluluğu, işlem izi. **md. 37 işletme projesi** → bütçe modülü (7 gün itiraz, kesinleşme notu). **md. 39 hesap verme, md. 41 denetim** → denetçi rolü (salt okunur), dönem özeti/denetim raporları.
- **KVKK Kurulu 2026/348 İlke Kararı**: borç listelerinin (ad, daire, tutar) ortak alanlara asılması hukuka aykırı; bireysel bildirim önerilir. Uygulama: borçlu listesi sakinlere **varsayılan olarak gizli**; yönetim isterse yalnızca kapı no veya isimli açabilir (ayarlar → şeffaflık); hatırlatmalar kişiye özel (uygulama içi/e-posta/SMS). `[DOĞRULANMASI GEREKİYOR: kararın uygulama içi bireysel görünüme etkisi; hukuki görüş alınmalı]`

## 10. Teknoloji kararı
Kullanıcının planı "TanStack Start + React + Lovable Cloud" öneriyordu; bu repo o stack'i içermiyor (`composer.json` PHP ≥8.3, `package.json` yalnızca Playwright). Kullanıcının ilk talebi ve mevcut standardı **Pure PHP 8.3 MVC** olduğundan uygulama `aidat/` altında framework'süz PHP ile yazıldı: PSR-4 autoload (composer'sız da çalışır), PDO prepared statement, SQLite (sıfır kurulum) + MySQL/MariaDB, oturum/CSRF/yetki/işlem izi/migrasyon/test altyapısı repo içinde. Frontend: el yapımı CSS tasarım sistemi + Alpine.js + Chart.js, tüm varlıklar self-host (CDN yok). Ayrıntı `TASARIM.md`, `GELISTIRME-REHBERI.md`.

## 11. Kabul ölçütleri ve doğrulama
- Her menü gerçek ekrana gider; ölü bağlantı yok (`tests/smoke.sh` tüm GET rotalarını tarar).
- Sunucu tarafı doğrulama ve Türkçe hata mesajı (Validator), yükleniyor/boş/hata/başarı durumları (toast, boş durum bileşeni).
- Yönetici ve sakin aynı finansal kaydı kendi kapsamında görür (servisler ortak).
- Kısmi ödeme, fazla ödeme, devir, iptal, kapalı dönem, mükerrer tahakkuk senaryoları PHPUnit ile test edildi (`tests/Unit`).
- 390 px mobil ve masaüstü görünümünde taşma yok (Playwright `tests/screenshot.mjs`, `tests/overflow.mjs`).
- Her ekranın özgün başlık/açıklama ve `og:` metadatası vardır (layout).
