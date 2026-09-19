# Türkiye Aidat / Site Yönetim Yazılımları – Ürün ve Özellik Araştırma Raporu

## 0. Yöntem ve kısıt notu (önemli)

- Bu oturumda **WebFetch ve curl ile hiçbir dış sayfa doğrudan açılamadı** (ağ egress proxy'si tüm alan adlarını – apsiyon.com, aidatim.com, aidatpro.com.tr, yonetisim.com, play.google.com, apps.apple.com, mevzuat.gov.tr, buildium.com, condocontrol.com vb. – "EGRESS_BLOCKED / CONNECT 403" ile engelledi).
- Tüm bulgular **WebSearch'ün sayfa içeriğinden ürettiği alıntı/özetlere** dayanır. Bu nedenle:
  - Aşağıda tırnak içinde verilen menü adları ve cümleler arama sonuçlarında **ürünün kendi sayfasından alıntılanmış** ifadelerdir.
  - Ekran görüntüsü, form alanı ve açılır liste seçeneklerinin tam dökümü doğrudan görülemedi; bu tür detaylar yalnızca kaynakta yazılı olduğu kadarıyla verildi ve gerektiğinde **[DOĞRULANMASI GEREKİYOR]** ile işaretlendi.
  - Hiçbir özellik uydurulmadı. Sayfası bulunamayan ürünler açıkça "bulunamadı" olarak yazıldı.
- Tarih bağlamı: Eylül 2026. Bazı kaynaklar 2026 tarihli (ör. KVKK 2026/348 ilke kararı).

---

## 1. Ürün bazlı özet

### 1.1 Apsiyon (apsiyon.com) – pazar lideri, orta/büyük site ve yönetim şirketleri
Kaynaklar: https://www.apsiyon.com/en/products/apsiyon , https://www.apsiyon.com/en , https://www.apsiyon.com/en/products/apsis , https://destek.apsiyon.com/... (aşağıda tek tek)

- Ölçek: "8.500 site ve yaşam alanında, 700 binden fazla konutta, 1,5 milyonu aşkın kullanıcı" (https://www.apsiyon.com/en). 'Site Yöneticisinin Dijital Asistanı' sloganı.
- Borç/tahakkuk: "aidat, ısınma, avans, demirbaş" gibi **istenildiği kadar borç kalemi** tanımlanabiliyor; ısınma/yakıt faturaları **sayaç okuma verisine göre** dairelere dağıtılabiliyor; aidat "malik, kiracı, daire ve kategori ayrımına göre otomatik dağıtılıyor" (arama özeti: https://yandex.com.tr/yacevap/c/ekonomi-ve-finans/q/apsiyon-aidat-takibi-nasil-yapilir-2841448959 ve https://www.apsiyon.com/en/products/apsis).
- Sakin tarafı: sakinler kendilerine verilen **10 haneli kod** ile tüm ödeme kanallarından borç sorgulayıp ödeyebiliyor; kredi kartı ile online ödeme; **icra takibi başlatma ve tahsilat sürecini izleme** (aynı kaynak).
- Destek merkezinde görülen **gerçek menü yolları** (destek.apsiyon.com):
  - "Ayarlar > Parametreler" → "Özelleştirilebilir makbuz/fatura çıktısı" seçeneği; "Ayarlar > Rapor Ayarları" → yeni rapor şablonu; "Finans > Aidat İşlemleri > Aidat Makbuzları" → makbuz seçip yazıcı simgesinden "Makbuz Çıktısı" (https://destek.apsiyon.com/tr/support/solutions/articles/65000170895-%C3%96zelle%C5%9Ftirilebilir-makbuz-ve-fatura-c-kt-s-)
  - "Raporlar > Belgeler > Apsiyon Pos Nakit Akış Raporu" (https://destek.apsiyon.com/tr/support/solutions/articles/65000178354-apsiyon-pos-nakit-ak-%C5%9F-raporu)
  - Finans raporları içinde "Özet Gelir Gider Raporu" ↔ Muhasebe raporları içinde "Gelir Tablosu" (https://destek.apsiyon.com/tr/support/solutions/articles/65000139799-%C3%96zet-gelir-gider-raporu-ile-gelir-tablosu-kar%C5%9F-la%C5%9Ft-rma)
  - Finans raporları içinde "Dönemsel Bakiye Listesi" ↔ Muhasebe "Mizan" (120 kişi hesabı bakiyesi); rapor bitiş tarihi ve mahsup tarihi eşit olmalı (https://destek.apsiyon.com/tr/support/solutions/articles/65000139509-d%C3%B6nemsel-bakiye-listesi-ile-mizan-kar%C5%9F-la%C5%9Ft-rma)
  - "WhatsApp Bildirimi" alanı ve "WhatsApp Durum Raporu"; gönderilen e-posta/SMS raporları (https://destek.apsiyon.com/tr/support/solutions/articles/65000189091-whatsapp-durum-raporu)
  - "İş Takibi" modülü ve "İş Takibi Durum Raporu" (departman, birim, personel, öncelik; tarih ve durum filtreleri) (https://destek.apsiyon.com/tr/support/solutions/articles/65000139790-%C4%B0%C5%9F-takibi-nas-l-yap-l-r-)
  - "Apsiyon Aktarım (import dosyası)" (https://destek.apsiyon.com/tr/support/solutions/articles/65000175337-apsiyon-aktar-m-import-dosyas-)
  - Banka entegrasyonu: hesap hareketleri Apsiyon'a düşer, ödeme açıklamasındaki ayırt edici bilgiye göre **sakinlerle otomatik eşleştirme** (https://destek.apsiyon.com/tr/support/solutions/articles/65000139530-apsiyon-banka-entegrasyonu , https://destek.apsiyon.com/tr/support/solutions/articles/65000139773-banka-hareketlerinin-apsiyon-a-aktar-lmas-)
  - Gider fişi: evrak türü seçimi; daha önce fatura girildiyse "Ödeme Makbuzu" seçilir; gider hesabı olarak faturaya bağlı **masraf kalemi**; hizmeti veren **firma/kişi** seçimi (arama özeti, aynı destek merkezi)
  - Apsis eğitim videoları başlıkları: "Kişiler ve Bağımsız Bölümler", "Toplu Borçlandırma", "Banka Hareketleri", "Borç Makbuzları ve Tahsilat Makbuzları", "Gelir ve Gider Evrakları Kayıt İşlemleri"; Bağımsız Bölümler'den **malik, kiracı veya sakin** ekleme; Finans/Banka Hareketleri sekmesi (https://destek.apsiyon.com/tr/support/solutions/articles/65000182372-apsis-e%C4%9Fitim-videolar-)
  - "Daireden Çıkış yapan Malik / Kiracı Finansal İşlemleri (Bakiye İşlemleri)" (https://destek.apsiyon.com/tr/support/solutions/articles/65000173139-...) ve "Ev sahipleri ve kiracıların devreden borç ve alacakları Apsiyon'a nasıl eklenir" (http://yardim.apsiyon.com/tr/articles/1170501-...)
  - "Toplu bir şekilde e-posta ile borç bildirimi" (https://yardim.apsiyon.com/yasam-alaninizdaki-iletisim/toplu-bir-sekilde-e-posta-ile-borc-bildirimi-nasil-yapilir); "Kira Modülü" koleksiyonu (http://yardim.apsiyon.com/tr/collections/127564-apsiyon-a-giris-kurulum)
- Apsiyon Yönetici (mobil): "özet ekrandan tüm işleri görme, finansal durum ve dağılımlar, kişiler, **banka hareketlerini onaylama/silme**, yapılacaklar listesi, **bekleyen sakinleri onaylama**, personel iletişimi, yönetim kararlarını izleme"; modül listesi: "finansal yönetim, aidat tahsilatı, raporlama, denetim, muhasebe, iletişim, güvenlik hizmetleri, personel hizmetleri, online ödeme, gelir-gider tanımlama" (https://play.google.com/store/apps/details?id=com.apsiyon.manager , https://www.apsiyon.com/en/management-mobile-app)
- Apsiyon sakin uygulaması: borç görüntüleme ve **taksitli ödeme**, kira kontratı, "Site Panosu" (yardım talebi/ilan), "Ziyaretçi" kaydı, ortak alan (tenis kortu, havuz, sauna, spor salonu) rezervasyonu, talep/şikâyet, anket, forum, galeri, ücretsiz web sitesi; **Apsiyon Life**: otomatik kredi kartı talimatı verenlere ayrıcalık platformu (https://apps.apple.com/tr/app/apsiyon/id742594884?l=tr , https://www.apsiyon.com/en/resident/mobile-app , https://www.apsiyon.com/en/resident/apsiyon-life)
- Personel: bordro hesaplama, bordro kesinleştirme, maaş ödemesi; doğum günü/rezervasyon otomatik bildirimleri; anket (arama özeti; birincil sayfa açılamadı) [DOĞRULANMASI GEREKİYOR]
- Ek ürünler: Apsiyon Sigorta (ortak alan sigortası) (https://apsiyonsigorta.com/); Yapı Kredi ve Garanti BBVA iş birlikleri ("Apsiyon Hizmet Bedeli Muafiyeti") (https://www.yapikredi.com.tr/isletme/isinize-ozel-firsatlar/site-apartman-yonetimi/apsiyon-isbirligi , https://www.garantibbva.com.tr/kampanyalar/musteri-ol-apsiyon-kampanyasi)
- Güvenlik: şifreleme, denetim (audit) logları, IP kısıtlaması, iki faktörlü doğrulama (https://www.apsiyon.com/en/products/apsis)
- Fiyat: "daire başı aylık 1 TL'den başlayan" (https://www.milliyet.com.tr/emlak/apsiyon-hizmetleri-1-liradan-basliyor-67745); şikâyet sitelerinde 1,38 TL+KDV/daire ve kart ödemelerinde sakinden alınan "hizmet bedeli" (ör. 105 TL) şikâyetleri (https://www.sikayetvar.com/apsiyon/hizmet-bedeli) – kullanıcı beyanı, resmi değil.

### 1.2 Apsiyon Apsis ("Apsis Yönetim") – küçük apartman/site
- Küçük ölçekli yaşam alanları için: "kart ile online aidat tahsilatı, banka entegrasyonu ile anlık hareket takibi, **ücretsiz web sitesi**, gelir-gider takibi, ihtiyaca uygun raporlamalar"; sakinler web sitesinden aidat detayını, duyuruları izler, talep/şikâyet iletir; toplu SMS/e-posta; 1 ay ücretsiz deneme (https://www.apsiyon.com/en/products/apsis , https://www.teknotalk.com/apartmanlara-ve-kucuk-sitelere-ozel-yonetim-yazilimi-apsiyon-apsis-70921/ , https://www.apsiyon.com/urunler/apsis/demo-olustur)

### 1.3 Aidatım (aidatim.com / aidatim.pro) – yönetim şirketleri + apartman
- "dönemsel borçlandırma, tahsilat, gecikme, gelir-gider, kasa ve raporlama işlemlerini tek kayıt düzeninde"; "**Dönem, borç türü ve dağıtım tercihine göre** birden fazla bağımsız bölüm için toplu tahakkuk"; "yapı kararlarınıza uygun **gecikme kurallarını otomatik** uygulayın. **Fazla ödeme, devir ve düzeltmeleri iz bırakacak şekilde** yönetin"; "Daire ve dönem bazında güncel tabloyu dışa aktarın"; sakinlerin kendi hesabına erişimi (https://aidatim.com/cozumler/aidat-takip-programi)
- Kurumsal: "Türkiye'nin ilk ve tek bulut tabanlı yönetim şirketi yazılımı" iddiası; "gelir/gider özetleri, aylık/yıllık bilançolar, bireysel ve cari hesap özetleri, sanal POS ve banka işlemleri raporları tek tıkla"; sakinlere tek çekim/taksit ödeme (https://www.aidatim.com/kurumsal , https://www.aidatim.com/ , https://www.aidatim.pro/cozumler/)
- Fiyat sayfası var (https://www.aidatim.com/fiyatlar) ancak tutarlar arama özetlerinde görünmedi [DOĞRULANMASI GEREKİYOR]. Aylık/yıllık paket modeli (https://www.aidatim.com/hizmet-sozlesmesi). Mobil: "Aidatım" (https://play.google.com/store/apps/details?id=com.tr.bi.aidatim).

### 1.4 AidatPro (aidatpro.com.tr)
- "aidat, ödeme, gider, duyuru, arıza/talep, güvenlik ve raporlama süreçlerini tek merkezde"; her daire için borç, ödeme geçmişi, kalan bakiye ayrı; **Duyuru** (toplantı, bakım, ödeme hatırlatma, genel bilgilendirme); **Arıza ve Talep** (asansör, kapı, aydınlatma, temizlik, bakım; açık/işlemde/tamamlandı); **Raporlar** (aidat, tahsilat, borç, ödeme, gider; PDF/Excel); **Gider Yönetimi** (kategori, tarih, açıklama bazlı); **Sakin Paneli / "Mesken Paneli"** (güncel borç, ödeme durumu, eski dönem borçları) (https://aidatpro.com.tr/ozellikler/aidat-ve-tahsilat-takibi , https://aidatpro.com.tr/blog/mesken-paneli-ne-i-se-yarar , https://aidatpro.com.tr/blog/aidat-takip-programi-nedir). Fiyat bilgisi bulunamadı.

### 1.5 Google Play / App Store "Aidat Takip" uygulamaları (küçük apartmanlar, kendi kendini yöneten binalar)
- **Aidat Takip (ETABİB Soft, com.etabibsoft.aidattakip)**: site/apartman ve özel okullar için; "geciken ödemeleri görme, ara ödeme/borç ekleme, indirim oluşturma, kullanıcı rolleri, SMS bildirimi" (https://play.google.com/store/apps/details?id=com.etabibsoft.aidattakip)
- **Aidat Takip (askinatik, App Inventor)**: birden fazla bina tanımlama, birimlere üye ekleme, **özel gelir-gider kategorileri** (https://play.google.com/store/apps/details?id=appinventor.ai_askinatik.AidatTakip)
- **Aidat Takip (com.mbsiteaidat)**: taşınan sakinlerin hesaba erişimini engelleme kontrolü (https://play.google.com/store/apps/details?id=com.mbsiteaidat)
- **Aidat Takip Sistemi (Yönetimcell)**: Yönetimcell müşterisi sitelerin sakinleri için ücretsiz sakin uygulaması; borç durumu, kredi kartı ile ödeme; yönetici açarsa **diğer dairelerin borç/alacak durumu ve yönetimin gelir-gideri** raporu (https://apps.apple.com/tr/app/aidat-takip-sistemi/id6447964574 , https://www.yonetimcell.com/)
- **Site & Apartman Yönetimi (Kodexus)**: aidat borç takibi, daire/blok yönetimi, **makbuz oluşturma ve paylaşma**, gelir-gider, ödeme geçmişi, aylık rapor, veri dışa aktarma (https://play.google.com/store/apps/details?id=com.kodexus.siteapartman&hl=tr)
- **Aidat360**: gelir/gider ve kasa durumu Excel/PDF; ay ve kategori filtresi; işlem başına makbuz; sakin kendi borcunu görür (https://play.google.com/store/apps/details?id=com.entersoftteam.apartmangelirgider.apartmangelirgider , https://aidat360.web.app/)
- **AidatTakibi.net**: dernek/kulüp/okul/site; ücretsiz plan: aidat & gider takibi, PDF rapor, sınırsız üye/birim, tek yönetici; Profesyonel: çok yönetici, bulut yedek, otomatik bildirim – ₺49/ay, ₺499,99/yıl, ₺999,99 ömür boyu (https://aidattakibi.net/)
- **VT Site Yönetim – Aidat Takip**: aidat takibi, otomatik hatırlatma, duyuru (grup/kişi), borç ve ödeme bildirimi, yetkili giriş, yönetim paneli (https://apps.apple.com/tr/app/vt-site-yonetim-aidat-takip/id6670739009?l=tr)
- **Aidat Takibi (id1668020853)**: sakin ekleme (iletişim bilgisi), aylık giderler, aidat belirleme, ödeme kaydı, sakin bakiye durumu (https://apps.apple.com/tr/app/aidat-takibi/id1668020853?l=tr)
- **Apartman ve Site Yönetimi (cnrapps)**: makbuz üretme + gelir/gider takibi (https://play.google.com/store/apps/details?id=com.cnrapps.yonetici)

### 1.6 "Bina Yönetim Sistemi" → SiteBYS (sitebys.com)
- "aidat ve gelir yönetimi, fatura ve giderler, kasa-banka, gecikme faizi"; "işletme defteri, gelir, gider, kasa-banka ve kapsamlı finansal raporlama"; "gelir, gider, kasa, banka ve cari işlemlerle ilgili tüm raporlar anında"; banka ekstre entegrasyonu, sakin web erişimi, web sitesi ve Excel entegrasyonu; sakin paneli + **Iyzico** ile online ödeme → tahsilat listesine düşer; "Bilgilendirme Modülü" (https://sitebys.com/ozellikler-bina-yonetim-programi/ , https://sitebys.com/site-yonetim-programinda-gider-takibi/ , https://sitebys.com/aidat-ve-gider-takip-programi-bilgilendirme-modulu/)

### 1.7 "Konut Yönetim" → Konsiyon (Google Play adı: "Konsiyon – Konut Yönetim Otomasyonu")
- 30.000+ site/apartman, 3 milyon+ kullanıcı iddiası; **otomatik banka entegrasyonu 0 maliyet**, "POS komisyon farkı yok, Konsiyon hizmet bedeli almaz (banka oranı ör. %1,64)"; verinin 10 dakikada bir yedeği (https://konsiyon.com/ , https://konsiyon.com/banka-entegrasyonu , https://play.google.com/store/apps/details?id=com.konsiyonproje.konsiyon)
- Modüller: aidat hesaplama/gecikme faizi, gelir-gider, **İcra Takip** (geciken ödemeleri listeleyip avukata iletme), **Personel hesapları ve yetki kısıtlama**, **Karar Defteri** (dijital), **Ziyaretçi/misafir kaydı**, **Rezervasyon** (havuz, fitness), **Anket ve oylama**, **Toplantı organizasyonu**, **Plaka Tanıma Sistemi entegrasyonu** (bariyer), WhatsApp iletişimi, makbuz ve hesap ekstresini sakin telefonuna gönderme; **her tahsilat için otomatik makbuz** (https://konsiyon.com/site-yonetim-programi-ile-apartman-islemlerini-dijitallestirin-b-5411 , https://konsiyon.com/site-yonetimi-icra-sureci-aidat-borclarinda-hukuki-takip-rehberi-b-4768)
- Raporlar: "hesap planı, yevmiye defteri, mizan, işletme defteri, bilanço, işletme projesi, cari hesap ekstreleri, kasa ve banka raporları, gelir-gider raporları, yıllık/aylık bütçe raporları", "daire hesap ekstreleri, üye raporları, kasa durum raporları, üye etiket raporları" (https://konsiyon.com/site-yonetimi-performans-raporu-nasil-hazirlanir-ve-degerlendirilir-b-5628 , https://konsiyon.com/is-merkezi-yonetim-programi)
- Ayrıca Senyonet'in "Konut Yönetim" adlı ayrı bir Google Play uygulaması vardır (https://play.google.com/store/apps/details?id=net.senyonet.konutyonetim) – içerik detayı alınamadı.

### 1.8 "e-Yönetici" → e-Yönetim Sistemi / eyon (eyonetimnet.com)
- Cüzi yıllık ücret; "banka hesap ekstresindeki gelir ve giderleri otomatik yükleme"; "**denetçiye verilmiş şifre** ile denetçi istediği zaman hesapları kontrol edebiliyor"; "daire sakinleri şifreleriyle girip hesaplarını kontrol edebiliyor" (https://www.eyonetimnet.com/)

### 1.9 YÖNETİCİ (yoneticiprogrami.com) – Windows, yerel veri, küçük/orta site
- Kullanım dersleri başlıkları = modül listesi: "Site Ekleme, Lisanslama, Mesken Ekleme, Kişi İşlemleri, Aidat Dağıtma, Taksitlendirme, Sayaç Bilgisi ile Dağıtma, Mesken Bilgisi ile Dağıtma, Toplu Ödeme Alma, Kasa İşlemleri, Raporlama, Eposta Bildirimleri, SMS Bildirimleri, Site Tanımlamalar, Ajanda Modülü, Site Borçları Modülü, Personel Bilgileri Modülü, Şikayet ve Talepler Modülü, Karar Defteri Modülü, Demirbaş Modülü"; veriler kullanıcının bilgisayarında; 1/2/3 yıllık lisans, 15 gün ücretsiz (https://yoneticiprogrami.com/index.php?id=5&page=kullanimdersleri&type=written , https://www.yoneticiprogrami.com/)

### 1.10 Apartmanım (apartmanim.com.tr)
- "bakım takibi, gelir-gider yönetimi, duyurular, otomatik borçlandırma, SMS gönderimi"; birim kavramı (daire + dükkân = toplam birim); ilk 3 ay ücretsiz, sonra modül seçimi (https://apartmanim.com.tr/ , https://apartmanim.com.tr/ozellikler/aidat-takibi/)

### 1.11 Bulunamayan / farklı çıkan isimler
- **Sitemax**: Türkiye'de bu adla konut yönetim yazılımı bulunamadı; çıkan "SiteMax Systems" Kanada merkezli inşaat şantiye yönetim yazılımıdır (https://sitemaxsystems.com/). **YÖNETMAX** adlı bir yönetim hizmeti şirketi var (https://www.yonetmax.com.tr/), yazılım değil.
- **YönetimPlus**: yonetimplus.com "Yönetim Plus+" bir **profesyonel site yönetim hizmet şirketi**, yazılım ürünü değil (https://yonetimplus.com/). Benzer isimli MD Plus Yönetim de hizmet şirketi.
- **Aidat Cebimde / AidatCebimde**: hiçbir sonuç yok. "…Cebimde" isimli uygulamalar farklı alanlarda (Tarım Cebimde, Odam Cebimde).
- **Yönetici Asistanı**: bu adla ürün bulunamadı; Odyosi kendini "Apartman, Site Yöneticilerinin Mobil Asistanı" olarak konumlar (https://odyosi.com.tr/).
- **Siteyönetim.com, BinaBank, AidatOnline**: arama sonucu yok. (aidattakipsistemi.com "Site Sakini Aidat Sorgulama ve Kredi Kartı ile Ödeme Sistemi" adlı bir ürün çıktı: https://www.aidattakipsistemi.com/)

### 1.12 Araştırmada öne çıkan diğer Türk ürünler (menü haritası için kullanıldı)
- **Yönetişim** (yonetisim.com, 2013'ten beri): yönetici, denetçi, temsilci, kat maliki, kiracı aynı sistemde; **malik ve kiracı daire giriş-çıkış tarihine göre ayrı hesaplarda**; "işletme bütçesi (aidat/yatırım) oluştur → yönetim planına göre bağımsız bölümlere dağıt → istisna uygula → tek tıkla tahakkuk/borç planına çevir"; iş takibi, tesis envanteri/planlı-acil bakım, sarf ve demirbaş stoku, belge arşivi (rapor, sözleşme, tapu), personel girişi; mobil: online aidat ödeme, ekstre/bakiye, yönetimin paylaştığı finansal raporlar, öneri-talep-şikâyet (https://yonetisim.com/ozellikler-apartman-site-rezidans-finans , https://yonetisim.com/ozellikler-apartman-site-rezidans-yonetim , https://yonetisim.com/)
- **Sabilgi**: modüller "İş Takip, Mobil Acil Destek, Demirbaş Yönetimi, Duyuru/Bildirim, Borç/Alacak Tahakkuk, **Isı Gider Paylaşımı**, Kredi Kartı Online Tahsilat, Banka Bakiye Kontrolü ve Otomatik Tahsilat, Emlak Takip, **Araç Plaka Tanıma**"; raporlar "faaliyet raporu, denetim raporu, işletme gelir-gider raporu, yevmiye defteri, cari hesap dökümleri, mizan"; m² / arsa payı ile toplu borçlandırma; yönetici raporları (gelir-gider tablosu, kasa/banka durum, faaliyet, denetim kurulu raporu) **sakinlerle sistem üzerinden paylaşabilir** (https://sabilgi.com/ , https://sabilgi.com/site-apartman-yonetimi-yazilimi-gelir-gider-analizi.aspx , https://sabilgi.com/site-apartman-yonetimi-yazilimi-seffaf-yonetim.aspx , https://sabilgi.com/site-apartman-yonetimi-yazilimi-demirbas-stok-takibi.aspx)
- **Hursoft** (masaüstü/site muhasebe): "Yevmiye ve Kebir Defterleri Tek Düzen Hesap Kodları ile", "İşletme Defteri ve kasa defteri (günlük/haftalık/aylık/yıllık)", "Toplantı Karar Defteri, İşletme Projesi Raporu, Dönemsel Fark Raporu, Makbuz Basımı"; "daireleri tek tek veya toplu (blok, kat, tip bazında) **aidat, yakıt, su, demirbaş** gibi kalemlerle borçlandırma"; Personel ve Tedarikçi Yönetimi; havale/EFT'yi otomatik düşüp dijital makbuz üretme (https://www.hursoft.com.tr/Site-Muhasebe-Programi,DP-219.html , https://www.hursoft.com.tr/urun/site-yonetim-programi , https://www.hursoft.net/urun/site-yonetim-programi-site-aidat-takip-programi)
- **BYönetim**: 9,90 TL/daire/ay KDV dâhil, kurulum ücreti/komisyon yok, sistem SMS'leri ücretsiz, 6 ay ücretsiz; "Gelirlerim" modülü: **Toplu Gelir Ekle / Gelir Kayıtları / Ek Gelirler**; aidat dağıtımı **arsa payı veya eşit**; gecikme faizi otomasyonu + SMS/push; kişi ve bağımsız bölüm yönetimi; **personel ve bordro (net-brüt-SGK hesabı)**; dijital **karar defteri, duyuru, oylama** (https://byonetim.com.tr/ , https://byonetim.com.tr/ozellikler/gelir-yonetimi)
- **Dijiyon** (Cisoft): borçlandırma "**Sabit, M²'ye göre, hisse payına göre, bağımsız bölüm özelliklerine göre (1+1, 2+1, 3+1) ve kullanım durumuna göre (dükkan, daire, ofis)**"; **İşletme Projesi düzenleyip proje üzerinden borçlandırma**; raporlar "mizan, bilanço esası, gelir gider esası"; **İcra Takip modülü** (avukat atama/yetki); Mesken Paneli; anlaşmalı banka ile 5 yıla kadar ücretsiz (https://dijiyon.com/ , https://dijiyon.com/ozellikler , https://dijiyon.com/yazilim-cozumleri)
- **Net Yönetim**: ilk 6 ay ücretsiz; modüller "Blok, Birim/Mülk, Sakin, Aidat Grubu, Acil Bildirim, Şikâyet, Anket, Duyuru, E-posta, SMS"; **EHO** ile banka hareketleri otomatik okunup ödeme "ödendi" işaretlenir ve **makbuz otomatik üretilip sakine gönderilir**; SMS + WhatsApp hatırlatma; genel kurul ve denetim kurulu için hazır PDF raporlar (https://netyonetim.com/ , https://netyonetim.com/aidat-takip-programi , https://support.netyonetim.com/)
- **Yönetimcell**: "sınırsız üye borçlandırma seçenekleri (**aidat, yakıt, su, demirbaş, ek gider** vb.)", "son ödeme tarihine göre **günlük hesaplanan gecikme faizi**", hediye SMS, 30 gün deneme; raporlar yazdırılabilir/e-posta/Excel (https://www.yonetimcell.com/site-apartman-yonetim-program-ozellikleri.html , https://www.yonetimcell.com/aidat-ve-gider-takip-programi-fiyatlari.html)
- **Odyosi**: "aidat tahsilat otomasyonu, banka & sanal POS, yapay zekâ destekli ön muhasebe, **denetim raporları**, sakin uygulaması"; SMS/e-posta/**IVR sesli arama** hatırlatma senaryoları; ödeme linki, 3D Secure, taksit; YZ: ödeyeni bul, işlemi sınıfla, hesap kodu öner, tolerans dışı hareketi uyar; **Denetçi için özel panel tanımı** veya tek tıkla denetim raporu; **mülk sahibi kiracısının borcunu görebilir** (https://odyosi.com/ , https://apps.apple.com/tr/app/odyosi/id6443723171?l=tr)
- **Apartsoft**: toplu borçlandırma, her ay belirli günde otomatik aidat, banka ödemeleri/tahsilatlar otomatik aktarım, cari takip, Excel-PDF raporlar, toplu SMS/e-posta ile **borç ekstresi gönderimi**; 30 gün deneme (https://www.apartsoft.com/Features , https://www.apartsoft.com/)
- **Aidat.ai**: 17.040+ konut; otomatik aidat, online ödeme, duyuru, rezervasyon; "akıllı fatura sistemi – **fatura anomalisi tespiti**", bütçe/kalem bazlı harcama takibi (https://aidat.ai/)
- **APYS**: borç/ödeme, SMS/e-posta bildirim, gelir-gider dengesi/ödeme oranları/daire durumu analitiği, filtrelenebilir dışa aktarılabilir raporlar, **çoklu site merkezi yönetim**, asansör/jeneratör bakım takvimi (https://apys.app/)
- **Blok Görevlisi** (ücretsiz): aidat, online ödeme, duyuru, kapı giriş logları/güvenlik bildirimi, grup sohbeti, belge paylaşımı (toplantı tutanağı, sözleşme, fatura), **kiracı ve malik borçlarının ayrı yönetimi** (https://blokgorevlisi.com/)
- **Kendin Yönet**: aidat tahakkuku, ödeme takibi, borç raporları, banka mutabakatı, kredi kartı; gider kategorileri (bakım, temizlik, güvenlik, yakıt) belgeli; sakin: anket, mesajlaşma, arıza bildirimi (https://kendinyonet.com/ , https://kendinyonet.com/cozumler/site-sakinleri)
- **SiteApp**: modüler fiyatlama; "**QR kodlu makbuz**", "YZ destekli belge okuma", "**TBK uyumlu FIFO ödeme önceliği**", otopark modülü (daire bazlı kota, **LPR plaka tanıma**), KVKK uyumlu ziyaretçi kaydı, kargo takibi, demirbaş/sarf (personel zimmeti, QR kodlu cihaz takibi, "stok bitiyor" uyarısı) (https://siteapp.com.tr/modules)
- **Vatan Site Yönetimi**: "Faaliyet raporu: gelirler, giderler, sakinlerden alacaklar, carilere borçlar, avans bakiyeler, kasa bakiyeleri"; personel ödemeleri/izin/ay sonu raporu; sakin uygulamasında **site genel harcamaları** görüntüleme, anket, mesajlaşma; günde 6 yedek (https://www.vatansiteyonetimi.com/site-yonetim-yazilimi-ozellikler , https://vatansiteyonetimi.com/site-yonetim-programi)
- **Waysim**: aidat kalemine fatura/dekont yükleyip üyelere açma; çoktan seçmeli sınırsız anket/oylama, kilitleyip raporlama; çok kullanıcılı (https://waysim.com/site_yonetimi.asp)
- **Aidatium**: asansör kayıtları/belgeleri, bakım planı, çoklu rol paneli (yönetici/personel/sakin), QR kod ve ödeme linki, kasa ve banka modülü, aylık/yıllık PDF-Excel (https://aidatium.com.tr/ozellikler , https://aidatium.com.tr/aidat-takip-programi)
- **Ay Site/Apartman (yonetici.web.tr)**: ücretsiz; NETGSM SMS entegrasyonu; "Aidat Durumu" yıllık çizelge raporu; "HESAP İŞLEMLERİ → Karar Defteri" içinde **hazirun listesi** raporu (http://yonetici.web.tr/)

### 1.13 Uluslararası eşdeğerler (fikir kaynağı)
- **Buildium (Association)**: aidat/assessment tahsilatı, **violation (ihlal) takibi** (sahadan fotoğraflı kayıt, otomatik ihtar, malikin portalden çözmesi), bakım talepleri, "Resident Center" portalı (öde, talep aç, belge gör), tam defter-i kebir, hesap planı, tekrarlayan ücretler, **otomatik banka mutabakatı**, fon bazlı raporlama; mimari değişiklik (ARC) için özel akış yok (https://www.buildium.com/portfolios/association-management-software/ , https://www.gfhoa.com/hoa-app-review/buildium , https://managecasa.com/articles/managecasa-vs-buildium-which-platform-wins-for-hoa-management)
- **Condo Control**: **Amenity Booking** (blackout, tampon süre, kapasite, şart-koşul imzası, ücret), **Visitor Parking** (ön yetkilendirme, ziyaretçi park izni), **Package tracking** (kargo bildirimi), Service Requests, belge yönetimi, online oylama, güvenlik/konsiyerj kullanıcı rehberi (https://www.condocontrol.com/resident/amenity-booking/ , https://www.condocontrol.com/security/visitor-parking-management/ , https://support.condocontrol.com/hc/en-us/articles/203449390-Welcome-To-Condo-Control-Security-and-Concierge-User-Guide)
- **TownSq**: gruplara duyuru, aidat/assessment ödeme, bakım talepleri, rol bazlı belge erişimi, komite yönetimi, forum, **yasal bağlayıcı online oylama ve canlı ilerleme** (https://www.townsq.io/solutions/townsq-community , https://www.townsq.io/solutions/by-role/board-members)
- **Wild Apricot** (HOA'lar için üyelik yazılımı): web sitesi oluşturucu, e-posta, üye rehberi, üyelere özel alan, **otomatik üyelik ödemesi ve yenileme**, etkinlik kaydı (https://www.wildapricot.com/blog/hoa-software , https://www.capterra.com/p/76116/WildApricot/)

---

## 2. Birleşik menü haritası (tüm ürünlerden derlenmiş)

Aşağıdaki liste, yukarıdaki ürünlerde görülen menü/modül adlarının birleşimidir. Her başlıkta "görüldüğü ürünler" verilmiştir; alan ve seçenek listeleri **yalnızca kaynakta yazılı olanlardır**; sektör pratiğinden çıkarım yapılan yerler işaretlidir.

### 2.1 Tanımlar / Yapı
- **Site / Bina / Blok tanımı** – "Site Ekleme", "Site Tanımlamalar" (YÖNETİCİ); "Blok Yönetimi" (Net Yönetim); çok bloklu sitede blok bazlı gider dağıtımı (Edge Bilişim rehberi: https://edgebilisim.com/apartman-site-yonetim-aidat-yazilimi/).
- **Bağımsız Bölümler / Daireler / Meskenler / Birimler** – "Bağımsız Bölümler" (Apsiyon, BYönetim), "Mesken Ekleme" (YÖNETİCİ), "Birim/Mülk Yönetimi" (Net Yönetim), "birim" (Apartmanım). Alanlar (kaynaklarda geçen): blok, kat, daire no, **daire tipi** (1+1/2+1/3+1 – Dijiyon), **kullanım türü** (daire/dükkân/ofis – Dijiyon; konut/ticari – Apartmanım), **arsa payı**, **m²** (Sabilgi, Dijiyon), sayaç bilgisi (YÖNETİCİ "Sayaç Bilgisi ile Dağıtma"), otopark kotası (SiteApp).
- **Kişiler / Sakinler** – "Kişiler" (Apsiyon), "Kişi İşlemleri" (YÖNETİCİ), "Sakin Yönetimi" (Net Yönetim). **Kişi tipi açılır listesi: Malik (Kat Maliki / Ev Sahibi) – Kiracı – Sakin** (Apsiyon Apsis videoları; Yönetişim "malik ve kiracı ayrı hesap"; Blok Görevlisi "kiracı ve mülk sahibi borçları ayrı"). Alanlar: ad-soyad, telefon, e-posta (Aidat Takibi app), **giriş-çıkış tarihi** (Yönetişim), devreden borç/alacak (Apsiyon). Çıkış işlemi: "Daireden çıkış yapan malik/kiracı bakiye işlemleri" (Apsiyon), taşınanın erişimini kapatma (mbsiteaidat).
- **Aidat Grupları / Kategoriler** – "Aidat Grubu Yönetimi" (Net Yönetim); "kategori ayrımına göre dağıtım" (Apsiyon).
- **Personel / Kapıcı** – "Personel Bilgileri Modülü" (YÖNETİCİ), personel hesapları ve yetki (Konsiyon), "Personel ve Tedarikçi Yönetimi" (Hursoft), **bordro net-brüt-SGK** (BYönetim), maaş/izin/ay sonu raporu (Vatan), bordro kesinleştirme (Apsiyon – doğrulanmalı), personel girişi (Yönetişim).
- **Tedarikçi / Cari Firmalar** – gider fişinde firma/kişi seçimi (Apsiyon), "carilere borçlar" (Vatan), cari hesap ekstreleri (Konsiyon).
- **Demirbaş / Envanter** – "Demirbaş Modülü" (YÖNETİCİ), "Demirbaş Yönetimi" + stok (Sabilgi), personel zimmeti + QR kodlu cihaz + stok uyarısı (SiteApp), tesis envanteri (Yönetişim). Alanlar: ürün adı, marka/model, seri no, alım tarihi, garanti süresi, zimmetli personel (genel demirbaş yazılımı pratiği – https://sabilgi.com/site-apartman-yonetimi-yazilimi-demirbas-stok-takibi.aspx ; Konsiyon'a atfedilen "fatura bilgisi, garanti süresi, servis numarası, personele zimmet" ifadesi arama özetinde geçti [DOĞRULANMASI GEREKİYOR]).
- **Asansör / Teknik sistemler** – asansör kayıt ve belgeleri, bakım planı (Aidatium); asansör/jeneratör bakım takvimi (APYS); planlı/acil bakım (Yönetişim).

### 2.2 Finans – Borçlandırma / Tahakkuk
- Menü adları: "Toplu Borçlandırma" (Apsiyon), "Aidat Dağıtma / Taksitlendirme / Sayaç Bilgisi ile Dağıtma / Mesken Bilgisi ile Dağıtma" (YÖNETİCİ), "Toplu Gelir Ekle / Gelir Kayıtları / Ek Gelirler" (BYönetim), "Borç/Alacak Tahakkuk" (Sabilgi), "Site Borçları Modülü" (YÖNETİCİ – yönetimin üçüncü kişilere borçları).
- **Borç türü açılır listesi** (kaynaklarda geçenler): Aidat, Yakıt/Isınma, Su, Demirbaş, Ek Gider/Ek Aidat, Avans, Kira (Konsiyon), Gecikme faizi/tazminatı (otomatik kalem), ceza/indirim (ETABİB "indirim"), "Yakıt Gideri", "Personel Maaşı", "Demirbaş Katkı Payı" gibi kullanıcı tanımlı kalemler (Edge rehberi). Kaynaklar: Yönetimcell, Hursoft, Apsiyon, Konsiyon.
- **Dağıtım yöntemi açılır listesi**: Eşit / Arsa payına göre / m²'ye göre / Daire tipine göre / Kullanım türüne göre / Sabit tutar / Sayaç (endeks) verisine göre / Özel kural-istisna (Dijiyon, BYönetim, Sabilgi, YÖNETİCİ, Yönetişim, Apsiyon). Isı gider paylaşımı ve otomatik endeks hesaplama (Sabilgi https://sabilgi.com/site-apartman-yonetimi-yazilimi-otomatik-endex-hesaplama.aspx).
- Alanlar: dönem (ay/yıl), son ödeme tarihi, tutar, borçlu taraf (malik/kiracı), açıklama, tekrar (her ay belirli gün – Apartsoft), taksit planı (YÖNETİCİ).
- **Gecikme faizi ayarı**: oran (KMK %5/ay), hesap yöntemi (günlük – Yönetimcell), otomatik uygulama (Aidatım, Konsiyon, BYönetim, Net Yönetim).
- **İşletme Projesi / Bütçe** → tahakkuka dönüştürme (Yönetişim, Dijiyon), İşletme Projesi Raporu (Hursoft, Konsiyon).

### 2.3 Finans – Tahsilat
- Menü: "Aidat İşlemleri > Aidat Makbuzları", "Borç Makbuzları ve Tahsilat Makbuzları" (Apsiyon), "Toplu Ödeme Alma" (YÖNETİCİ), tahsilat listesi (SiteBYS).
- **Ödeme şekli açılır listesi**: Nakit / Havale-EFT / Kredi kartı (sanal POS) / Çek-Senet. Doğrudan bir site yazılımı ekranından okunamadı; genel tahsilat makbuzu pratiği (https://www.parasut.com/blog/tahsilat-makbuzunun-muhasebe-kaydi , https://www.logo.com.tr/blog/blog-detay/tahsilat-ve-odeme-yontemlerini-yakindan-taniyalim) ve ürünlerde nakit/banka/kart ayrımı (Hursoft, Konsiyon, Blok Görevlisi "kredi kartı ve nakit") [site yazılımı ekranı için DOĞRULANMASI GEREKİYOR].
- Alanlar: tarih, daire/kişi, tutar, ödeme şekli, kasa/banka hesabı, açıklama, dönem/borç eşleştirme, **FIFO (en eski borçtan) mahsup** (SiteApp "TBK uyumlu FIFO"), kısmi ödeme / fazla ödeme / mahsup / devir (Aidatım), taksit (Apsiyon, Odyosi).
- **Banka hareketleri ekranı**: "Banka Hareketleri" – içe aktar (ekstre Excel/entegrasyon), eşleştir, onayla/sil (Apsiyon Yönetici), otomatik eşleştirme (Apsiyon, Konsiyon, Sabilgi, Net Yönetim EHO, Hursoft, Vatan, Kendin Yönet).

### 2.4 Finans – Gider / Gelir / Kasa-Banka
- Menü: "Gelir ve Gider Evrakları Kayıt İşlemleri" (Apsiyon), "Fatura ve Giderler", "Kasa-Banka" (SiteBYS), "Kasa İşlemleri" (YÖNETİCİ), "Gider Yönetimi" (AidatPro), "Ek Gelirler" (BYönetim).
- Gider fişi alanları (Apsiyon): evrak türü (Fatura / Ödeme Makbuzu), masraf kalemi (gider hesabı), firma/kişi, tutar, tarih, kasa/banka; belge eki (fatura/dekont yükleme – Waysim, Kendin Yönet).
- **Gider kategorileri (tipik liste)**: Personel (kapıcı/güvenlik/temizlik/bahçıvan maaşı, SGK primi, kıdem, İSG), Elektrik (ortak alan), Su, Doğalgaz/Yakıt, Asansör bakım, Temizlik malzemesi, Güvenlik hizmeti, Bakım-onarım, Peyzaj, Sigorta, Yönetici ücreti, Demirbaş alımı, Noter/avukat/icra, Banka masrafı, Diğer (https://konsiyon.com/apartman-gelir-gider-takibi-profesyonel-yonetim-icin-kapsamli-rehber-b-5871 , https://www.imeryonetim.com/2025-2026-apartman-giderleri-ve-ortak-gider-paylasimi.html , https://www.yenisehiryonetim.com/apartman-ortak-giderleri-nelerdir-2026/). Sabit/değişken ayrımı (Konsiyon blog).
- Kasa/Banka: birden fazla banka hesabı, kasa devri, "kasa ve banka hesapları ayrı takip" (Aidatium, SiteBYS).
- **Muhasebe (opsiyonel)**: Tek Düzen Hesap Planı, yevmiye, kebir, mizan, bilanço, gelir tablosu (Hursoft, Konsiyon, Apsiyon Muhasebe raporları, Dijiyon "bilanço esası / gelir gider esası").

### 2.5 İletişim
- **Duyurular** (tüm ürünler; grup/kişi hedefleme – VT), **SMS / E-posta / Push / WhatsApp** (Apsiyon WhatsApp Bildirimi; Net Yönetim WhatsApp; Konsiyon), **IVR sesli arama** (Odyosi), okundu bilgisi (Edge rehberi), toplu borç ekstresi gönderimi (Apartsoft, Apsiyon), **Acil Bildirim** (Net Yönetim, Sabilgi "Mobil Acil Destek"), **Anket / Oylama** (Konsiyon, Waysim, BYönetim, Net Yönetim, Kendin Yönet, Vatan), **Site Panosu / forum / grup sohbeti** (Apsiyon, Blok Görevlisi), mesajlaşma (Vatan, Kendin Yönet).
- **Talep / Arıza / Şikâyet** – "Şikayet ve Talepler Modülü" (YÖNETİCİ), "Arıza ve Talep" (AidatPro: asansör, kapı, aydınlatma, temizlik, bakım; durumlar açık/işlemde/tamamlandı), "İş Takibi" (Apsiyon: departman/birim/personel/öncelik, durum raporu), personele/firmaya atama (Sabilgi), öneri-talep-şikâyet (Yönetişim).

### 2.6 Yönetişim / Belgeler
- **Karar Defteri / Toplantılar** – "Karar Defteri Modülü" (YÖNETİCİ), dijital karar defteri (Konsiyon, BYönetim), Toplantı Karar Defteri raporu (Hursoft), hazirun listesi (Ay), toplantı organizasyonu (Konsiyon), yönetim kararlarını izleme (Apsiyon Yönetici).
- **Belgeler / Arşiv** – toplantı tutanakları, sözleşmeler, faturalar (Blok Görevlisi), rapor/sözleşme/tapu arşivi (Yönetişim), aidat kalemine belge ekleme (Waysim).
- **Ajanda** (YÖNETİCİ), bakım takvimi (APYS).
- **İcra / Hukuk** – İcra Takip modülü, avukat atama (Dijiyon, Konsiyon), icra başlatma (Apsiyon).
- **Denetçi görünümü** – denetçi şifresi (e-Yönetim), denetçi paneli/tek tık denetim raporu (Odyosi), denetçi rolü (Yönetişim).

### 2.7 Güvenlik / Tesis
- **Ziyaretçi kaydı** (Apsiyon, Konsiyon, SiteApp KVKK uyumlu, Blok Görevlisi kapı logları), **araç/plaka & bariyer** (Konsiyon, Sabilgi, SiteApp LPR, kota), **kargo takibi** (SiteApp; Condo Control), **rezervasyon** (Apsiyon, Konsiyon, Aidat.ai; Condo Control kurallı), güvenlik personeli bildirimi (Blok Görevlisi).

### 2.8 Ayarlar
- "Ayarlar > Parametreler", "Ayarlar > Rapor Ayarları" (Apsiyon); makbuz şablonu; gecikme oranı; kullanıcı rolleri ve yetkiler (yönetici/denetçi/personel/sakin – Yönetişim, Aidatium, Konsiyon, ETABİB); 2FA, IP kısıtı, audit log (Apsiyon); SMS başlığı/paketi (Ay-NETGSM, Yönetimcell hediye SMS); banka entegrasyon tanımı; yedekleme (Konsiyon 10 dk, Vatan günde 6).

### 2.9 Sakin portalı (bkz. bölüm 6)

---

## 3. Raporlar (ürünlerde görülen tam liste)

| Rapor | Görüldüğü ürün(ler) | Kaynakta belirtilen sütun/filtre |
|---|---|---|
| Borç-Alacak / Borçlu Listesi / Bakiye Listesi | Apsiyon "Dönemsel Bakiye Listesi", Konsiyon, Sabilgi, Net Yönetim, Hursoft | Apsiyon: rapor bitiş tarihi + mahsup tarihi parametreleri; mizan 120 hesabı ile mutabık. Faaliyet raporunda "borçlu daire listesi, daire no, borç tutarı, borç süresi, toplam alacak" (https://gozdeyonetim.com/site-faaliyet-raporu-2025-2026/ , https://www.biyos.net/tr/destek/apartman-ve-site-yonetimlerinde-denetim-raporu-nasil-hazirlanir) |
| Daire / Cari Hesap Ekstresi | Konsiyon "daire hesap ekstreleri, cari hesap ekstreleri", Sabilgi "cari hesap dökümleri", Hursoft "makbuz ve ekstre yazdırma", Yönetişim "ekstre/bakiye", Aidatım "bireysel ve cari hesap özetleri" | tarih, açıklama, borç, alacak, bakiye (standart ekstre; sütun adı kaynakta yok) |
| Tahsilat Raporu | AidatPro, Sabilgi ("tahsilat"), Net Yönetim ("aylık/yıllık tahsilat ve borç raporları"), Ay "Aidat Durumu" (yıllık çizelge) | ay × daire matrisi (Ay) |
| Gelir-Gider Raporu / Özet | Apsiyon "Özet Gelir Gider Raporu", Sabilgi "işletme gelir-gider raporu", Konsiyon, Aidat360 (ay/kategori filtresi), Vatan | Gözde örneği: Devreden Bakiye, Toplam Gelirler (A), Toplam Giderler (B), dönem sonu Kasa/Banka Bakiyesi (https://gozdeyonetim.com/apartman-gelir-gider-tablosu-ornegi/) |
| Kasa Raporu / Kasa Defteri / Banka Raporu | Hursoft "Kasa Raporu", Konsiyon "kasa durum raporları, kasa ve banka raporları", SiteBYS, Aidatium | günlük/haftalık/aylık/yıllık (Hursoft) |
| İşletme Defteri | Hursoft, Konsiyon, SiteBYS, Sabilgi (yevmiye) | sol sayfa gelir / sağ sayfa gider düzeni (Gözde) |
| İşletme Projesi Raporu | Hursoft, Konsiyon, Dijiyon, Yönetişim (bütçe) | gider kalemleri, aylık/yıllık toplam, paylaşım esası (arsa payı/eşit), daire başına avans (https://kghukuk.av.tr/site-isletme-projesi-hazirlama-ornegi/ , https://blokgorevlisi.com/blog/apartman-isletme-projesi) |
| Bütçe (aylık/yıllık tahmini vs gerçekleşen) | Konsiyon, Aidat.ai | kalem bazında aşım/tasarruf (Aidat.ai) |
| Mizan / Bilanço / Gelir Tablosu / Yevmiye / Kebir / Hesap Planı | Hursoft, Konsiyon, Apsiyon (Muhasebe raporları), Dijiyon, Sabilgi | Tek Düzen hesap kodları |
| Faaliyet Raporu | Sabilgi, Vatan | Vatan: gelirler, giderler, sakinlerden alacaklar, carilere borçlar, avans bakiyeler, kasa bakiyeleri |
| Denetim (Kurulu) Raporu | Sabilgi, Odyosi (tek tık), Net Yönetim (denetim kurulu PDF) | 8 bölüm: başlık, dönem, gelir özeti, gider özeti, **kasa-banka mutabakatı**, borçlu listesi, denetçi görüşü, imza (https://www.imeryonetim.com/apartman-denetci-raporu-ornegi.html) |
| Dönemsel Fark Raporu | Hursoft | dönemler arası karşılaştırma |
| Borç Yaşlandırma | Edge Bilişim rehberi ("icra ve tahsilat süreçleri için borç yaşlandırma raporları") | ürün ekranında doğrudan görülmedi [DOĞRULANMASI GEREKİYOR] |
| Devir-Teslim | ürünlerde doğrudan bu adla görülmedi; yönetim şirketi rehberlerinde "karar defteri, kasa raporu, bilanço, mesken borç ve iletişim bilgileri, dönemsel bakiyeler, işletme defteri" listesi (https://gozdeyonetim.com/site-yonetimi-nasil-degistirilir/ , https://www.sehiryonetim.com/blog/site-yonetimlerinde-devir-teslim-islemleri-belgeler-ve-dikkat-edilmesi-gerekenler) | Şehir Yönetim: karar defteri, denetim defteri, işletme defteri, personel özlük dosyaları, arşiv belgeleri, envanter listesi |
| Pos Nakit Akış Raporu | Apsiyon ("Raporlar > Belgeler") | – |
| İletişim raporları | Apsiyon: WhatsApp Durum Raporu, SMS/e-posta gönderim raporları | – |
| İş Takibi Durum Raporu | Apsiyon | departman/birim/personel/öncelik; tarih, durum |
| Üye raporları / üye etiket raporu | Konsiyon | etiket baskısı |
| Personel raporları | Vatan (ay sonu raporu), BYönetim (bordro) | – |
| Anket sonuç raporu | Waysim, Edge | – |
| Dışa aktarma | hemen hepsinde PDF/Excel; e-posta ile gönderim (Yönetimcell) | – |

---

## 4. Makbuz / Tahsilat fişi

**Yasal zemin ve pratik**: Aidat makbuzu ticari fatura değildir; KMK md.36 gereği giderlerin belgeleriyle saklanması ve şeffaf kayıt zorunluluğu kapsamında düzenlenir. "Özellikle elden/nakit tahsilatlarda makbuz şarttır; havale/EFT'de dekont belge sayılır ama yönetim yine de kayıt tutmalıdır" (https://konsiyon.com/apartman-aidat-makbuzu-vermek-zorunlu-mu-yasal-yukumlulukler-ve-uygulamalar-b-3076 , https://www.yenisehiryonetim.com/aidat-makbuzu-2026/). Eskiden **koçanlı, karbon/otokopili "Tahsilat Makbuzları"** kullanılırdı (https://yandex.com.tr/yacevap/c/ekonomi-ve-finans/q/bina-yonetimi-aidat-makbuzunu-nasil-duzenler-2758203572).

**Matbu makbuz fiziksel standardı** (kırtasiye ürünleri): "Apartman Gelir Gider Makbuzu / Apartman Gider Makbuzu", A5 (14×21 cm) veya dar boy 9,5×18,5 cm, 57 gr otokopili, **1 asıl + 1 kopya**, 50 yaprak/cilt, **seri numaralı** (http://m.gncmatbaa.com/kategori/apartman-makbuzu.html , https://www.acilkirtasiye.com/ulas-apartman-gelir-gider-aidat-makbuzu-otocopili-1-asil-1-kopya-stk-004737 , https://moralofismarket.com/dar-apartman-gelir-gider-makbuzu-uysal-10lu).

**Tipik alanlar** (aptyonet şablonu + matbu ürün tarifleri):
- Başlık: "AİDAT / TAHSİLAT MAKBUZU", site/apartman adı ve adresi (kaşe alanı)
- **Seri – Sıra (Makbuz) No**: matbu seri; yazılımda otomatik numaralama ("parametreler bölümünden otomatik" – Yandex özeti; "dijital makbuzlar sistem tarafından otomatik numaralanır" – https://byonetim.com.tr/blog/aidat-makbuzu-nasil-duzenlenir-yoneticiler-icin-rehber-2026)
- Tarih
- Sayın / Ödeyen (ad-soyad), **Blok–Daire No**, malik/kiracı
- Açıklama / **Ödenen dönem** ("Mart 20.. / Ocak–Mart 20..")
- Tutar (rakamla) ve **"Yalnız …… Türk Lirası" (yazıyla)**
- Gider tanımı/kalem (aidat, yakıt, demirbaş…) – gider makbuzunda "gider tanımı ve tutar bölümü"
- Ödeme şekli (nakit/havale/kart) – aptyonet örneğinde alan olarak geçiyor
- "Yukarıda belirtilen döneme ait ortak gider (aidat) tahsil edilmiştir" ibaresi
- **Tahsil eden (yönetici) adı, imza, kaşe**; bazı şablonlarda ödeyen imzası
Kaynaklar: https://aptyonet.com/dilekceler/apartman-aidat-tahsilat-makbuzu-ornegi , https://www.imeryonetim.com/apartman-gider-makbuzu-nasil-doldurulur-orneklerle-detayli-anlatim.html (Excel'de yazıyla tutar makrosu), https://emlakkulisi.com/guncel/aidat-makbuzu-ornegi/945796

**Yazılımlardaki makbuz özellikleri**:
- Apsiyon: "Borç Makbuzu" ve "Tahsilat Makbuzu" ayrımı; "Özelleştirilebilir makbuz/fatura çıktısı" (Ayarlar > Rapor Ayarları şablonu); makbuz üzerinde yazıcı simgesi.
- Konsiyon: her tahsilat için otomatik makbuz, mobil uygulamaya iletim; makbuz + hesap ekstresi telefona gönderme.
- Net Yönetim EHO, Hursoft: banka eşleşmesinden **otomatik dijital makbuz** üretimi.
- SiteApp: **QR kodlu makbuz**. Kodexus/Aidat360: makbuz oluştur-paylaş (PDF).
- Tekil veya **toplu tahsilat makbuzu / gider makbuzu** oluşturma; e-makbuz'un basılı makbuzla aynı geçerlilikte olduğu ve e-posta ile iletildiği görüşü (https://byonetim.com.tr/blog/aidat-makbuzu-ve-online-tahsilat-yonetici-icin-seffaflik-2026) [hukuki geçerlilik iddiası DOĞRULANMASI GEREKİYOR].
- Önerilen tasarım (araştırmadan çıkarım): A5 tek sayfa, üstte site kaşesi/adı, sağ üst seri-sıra no ve tarih, gövdede daire/kişi/dönem/kalem tablosu, rakam+yazıyla tutar, ödeme şekli, altta tahsil eden imza-kaşe ve isteğe bağlı QR (makbuz doğrulama linki), "Asıl / Kopya" işareti (2 nüsha).

---

## 5. Yasal çerçeve özeti – 634 sayılı Kat Mülkiyeti Kanunu (KMK)

Birincil metin: https://www.mevzuat.gov.tr/MevzuatMetin/1.5.634.pdf (bu oturumda açılamadı; madde içerikleri aşağıdaki hukuk sitelerinden alınan alıntılarla verilmiştir; resmi metinle son kontrol önerilir). Konsolide metin: https://www.lexpera.com.tr/mevzuat/kanunlar/kat-mulkiyeti-kanunu-634 ; madde bazlı: https://app.e-uyar.com/madde/index/... ; https://mevzuat.mturkoglu.av.tr/belge/634/madde/29

- **md.20 – Genel giderlere katılma**: Kat malikleri (a) **kapıcı, kaloriferci, bahçıvan ve bekçi giderlerine ve bunlar için toplanacak avansa eşit olarak**; (b) sigorta primleri, ortak yerlerin bakım/koruma/**güçlendirme**/onarım giderleri, yönetici aylığı, ortak tesis işletme giderleri ve avanslarına **arsa payı oranında** katılır. Gider veya avans payını ödemeyen kat maliki, geciktiği günler için **aylık %5 gecikme tazminatı** öder. Oran 5711 sayılı Kanun (28.11.2007 RG) ile %10'dan %5'e indirildi; "güçlendirme" eklendi. (https://app.e-uyar.com/madde/index/6f25e284-0ad1-4554-a58d-740e503a3698 , https://kubrayildiz.av.tr/kmk-madde-20/ , https://www.resmigazete.gov.tr/eskiler/2007/11/20071128-1.htm , https://www.basaryalti.av.tr/?p=makaleler&id=68). Yazılım etkisi: gider kalemine "eşit / arsa payı" dağıtım anahtarı ve günlük bazda %5/ay gecikme hesabı.
- **md.28 – Yönetim planı**: Yönetim tarzını, kullanma maksat ve şeklini, yönetici ve denetçi ücretini ve diğer yönetim hususlarını düzenler; **bütün kat maliklerini bağlayan sözleşme hükmündedir** (https://www.turkhukuksitesi.com/mevzuat.php?mid=9703 , https://www.lexpera.com.tr/mevzuat/kanunlar/kat-mulkiyeti-kanunu-634). Yazılımda: gecikme oranı, dağıtım kuralı ve toplantı zamanı "yönetim planına göre" parametrik olmalı.
- **md.29 – Toplantı zamanı**: Kat malikleri kurulu **yılda en az bir kez**, yönetim planında gösterilen zamanda, gösterilmemişse **her takvim yılının ilk ayında** toplanır. Önemli sebep varsa yöneticinin/denetçinin/kat maliklerinin üçte birinin istemiyle, **en az 15 gün önce** imzalattırılan çağrı veya taahhütlü mektupla, sebep bildirilerek her zaman toplanabilir (https://mevzuat.mturkoglu.av.tr/belge/634/madde/29).
- **md.30 – Yeter sayı**: Kurul, kat maliklerinin **sayı ve arsa payı bakımından yarısından fazlasıyla** toplanır, **oy çokluğuyla** karar verir. İlk toplantı yapılamazsa ikinci toplantı **en geç 15 gün sonra**; karar yeter sayısı **katılanların salt çoğunluğu** (https://app.e-uyar.com/madde/index/3ec3fc94-aea9-41cc-b843-97a2e710611d).
- **md.31 – Oy hakkı**: Her kat maliki arsa payına bakılmaksızın **bir oy**; birden fazla bağımsız bölümü olan her bölüm için ayrı oy, ancak toplam oyların **üçte birinden fazla olamaz** (https://www.turkhukuksitesi.com/mevzuat.php?mid=9706).
- **md.32 – Karar defteri**: Kararlar, (1)'den başlayıp sıra ile giden sayfa numaralı ve **her sayfası noter mührüyle tasdikli** deftere yazılır; toplantıda bulunan kat malikleri imzalar; karşı oy verenler sebebini belirterek imzalar (https://www.lexpera.com.tr/mevzuat/kanunlar/kat-mulkiyeti-kanunu-634 arama özeti).
- **md.34 – Yönetici atanması**: Kat malikleri, aralarından veya dışarıdan bir kişiyi (Yönetici) ya da üç kişilik kurulu (Yönetim Kurulu) atar; **sekiz veya daha fazla bağımsız bölüm varsa yönetici atanması mecburidir** (aynı kaynak).
- **md.35 – Yöneticinin görevleri** (yönetim planında aksi yoksa): a) kurul kararlarını yerine getirmek; b) anagayrimenkulün amacına uygun kullanımı, korunması, bakım ve onarımı için tedbir; c) sigorta ettirmek; d) genel yönetim, koruma, onarım, temizlik, asansör, kalorifer, sıcak-soğuk hava işletmesi ve sigorta için **yönetim planındaki zamanda avans toplamak**; … i) borç ve yükümlerini yerine getirmeyen kat maliklerine **dava ve icra takibi**; j) toplanan para ve avansları **muteber bir bankada hesap açtırıp yatırmak**; k) kurulu toplantıya çağırmak (https://app.e-uyar.com/madde/index/2f1557e6-42d0-41d3-81e6-85d5bb7f88d2 , https://www.emlaksayfasi.com.tr/bilgi-odasi/kat-mulkiyeti-kanunu-madde-35-h68559.html). (Bentlerin tam listesi e-uyar'da; e/f/g/h bentleri alıntıda görülmedi [DOĞRULANMASI GEREKİYOR].)
- **md.36 – Defter tutulması ve belgelerin saklanması**: Yönetici, kurul kararlarını, protokolleri, ihtar ve tebligat özetlerini/tarihlerini ve **bütün giderleri** md.32'deki deftere **tarih sırasıyla** yazmak; defteri ve **giderlerin belgeleriyle diğer bütün belgeleri bir dosyada saklamak** zorundadır. Defter, **her takvim yılının bitiminden itibaren bir ay içinde notere kapattırılır**. Aykırılıkta md.33 son fıkrasındaki para cezası (https://app.e-uyar.com/madde/index/458a540d-1ea9-43f1-9369-a724dd7889cb). Yazılım etkisi: gider kaydı = belge eki + tarih sıralı işletme defteri çıktısı; yıllık defter kapanışı hatırlatıcısı.
- **md.37 – İşletme projesi**: Kurulca kabul edilmiş proje yoksa yönetici gecikmeksizin hazırlar; projede **bir yıllık tahmini gelir-gider** ve **her kat malikinin md.20'ye göre ödeyeceği tahmini avans tutarı** gösterilir; proje kat maliklerine **imza karşılığı veya taahhütlü mektupla** bildirilir; **7 gün içinde itiraz** edilirse kurul karar verir/yeni proje yapılır, itiraz yoksa **kesinleşir**; kesinleşen işletme projesi veya kurulun işletme giderlerine ilişkin kararları **İİK md.68/1 anlamında belge** niteliğindedir (https://app.e-uyar.com/madde/index/02a72592-8edc-4773-bcef-7cce6a69aa90 , https://senerlegal.com/docs/kat-mulkiyeti/kmk/md-37/ , https://asmhukuk.com/20170321/kat-mulkiyeti-kanununa-gore-isletme-projesine-itiraz-edilmesi/).
- **md.38 – Sorumluluk**: Yönetici kat maliklerine karşı **aynen bir vekil gibi** sorumludur (https://app.e-uyar.com/madde/index/6a57f7a6-6085-47da-81c1-54107776617b).
- **md.39 – Hesap verme**: Yönetim planındaki zamanda, yoksa **her takvim yılının birinci ayında**, o tarihe kadarki **gelir ve giderlerin hesabını** kurula verir; kat maliklerinin yarısı isterse her zaman hesap vermek zorundadır [ikinci cümle DOĞRULANMASI GEREKİYOR] (https://www.lexpera.com.tr/mevzuat/kanunlar/kat-mulkiyeti-kanunu-634).
- **md.40 – Yöneticinin hakları**: Kural olarak **vekilin haklarına** sahiptir; ücret vb. (aynı kaynak).
- **md.41 – Yönetimin denetlenmesi**: Kurul yöneticiyi sürekli denetler, haklı sebeple her zaman değiştirebilir. Hesap denetimi için yönetim planında zaman yoksa **her üç ayda bir** yapılır; haklı sebeple her zaman yapılabilir. Kurul, denetimi **sayı ve arsa payı çoğunluğuyla seçeceği bir denetçiye veya üç kişilik denetim kuruluna** verebilir. Denetçi, yönetim planındaki zamanda, yoksa **her takvim yılının birinci ayında** kurula **raporla** denetim sonucunu ve yönetim tarzı hakkındaki görüşünü bildirir; raporu ve kararlarını **(1)'den başlayan sayfa numaralı, her sayfası noter tasdikli deftere** tarih koyup imzalar (https://app.e-uyar.com/madde/index/6f1d1688-bd79-4f7b-a77e-6a1ff6754126 , https://www.mulkiyet.org/kat-mulkiyet-kanunu/besinci-bolum). Yazılım etkisi: denetçi rolü, 3 aylık denetim dönemi raporu, "denetim defteri" çıktısı.
- **KVKK 2026/348 sayılı İlke Kararı (18.02.2026, RG 31.03.2026)**: Aidat, avans, demirbaş gideri vb. borç bilgilerinin (ad-soyad, daire no, tutar) asansör/bina girişi gibi **ortak alanlara asılması hukuka aykırı**; bireysel bildirim, e-posta, kapalı zarf veya yönetim yazılımı üzerinden özel bildirim önerilir (https://www.kvkk.gov.tr/Icerik/8719/... , https://www.alomaliye.com/2026/03/31/kvkkdan-apartman-ve-siteler-icin-kritik-karar-borc-listelerini-ortak-alanlara-asmak-hukuka-aykiri/ , https://www.ozgureralp.com/apartman-ve-sitelerde-aidat-borclulari-listesi-asmak-yasaklandi-kvkk-ilke-karari-2026-348/).

---

## 6. Şeffaflık özellikleri – sakin portalı

Sakinin **kendi verisi** (tüm ürünlerde ortak): güncel borç, geçmiş dönem borçları, ödeme geçmişi, ekstre/bakiye, makbuz, duyurular, talep/arıza durumu (Apsiyon, AidatPro, Yönetişim, Aidatım, Konsiyon, Sabilgi, Net Yönetim, BYönetim, Kendin Yönet, VT, Aidat360).

Sakinin **bina geneli** verisi:
- **Gelir-gider / kasa durumu**: Yönetimcell – yönetici açarsa "yönetimin gelir-giderini detaylı takip"; Vatan – sakin "site genel harcamalarını" görür; Sabilgi – yönetici gelir-gider tablosu, kasa/banka durumu, faaliyet ve denetim kurulu raporunu sistemden paylaşır; Yönetişim – "yönetimin sağladığı finansal raporlar"; Kendin Yönet – "sakinlerinize şeffaf muhasebe"; Odyosi – "sakinler ve yöneticiler aynı bilgi ve raporlara bağlanır"; e-Yönetim – sakin şifresiyle hesapları kontrol eder; Apsiyon mobil – "binanın/topluluğun finansal durumunu anlık görme" (https://www.apsiyon.com/en/resident/mobile-app , https://www.yonetimcell.com/site-apartman-yonetim-program-ozellikleri.html , https://www.vatansiteyonetimi.com/site-yonetim-yazilimi-ozellikler , https://sabilgi.com/site-apartman-yonetimi-yazilimi-seffaf-yonetim.aspx).
- **Diğer dairelerin borçları**: Yönetimcell'de yöneticinin açabildiği "diğer dairelerin borç/alacak durumu" raporu var; ancak KVKK 2026/348 sonrası kişiselleştirilmiş borç listesi paylaşımı riskli. Uyumlu tasarım: **anonim/toplu** ("toplam tahsilat oranı %78, 6 daire borçlu, toplam alacak X TL") veya yalnızca **denetçi/yönetim rolüne** açık liste. Mülk sahibinin **kendi kiracısının** borcunu görmesi Odyosi'de mevcut (meşru menfaat).
- **Belgeler / karar defteri / tutanaklar**: Blok Görevlisi (tutanak, sözleşme, fatura), Yönetişim (arşiv), Konsiyon (karar arşivi geçmişe dönük), Waysim (aidat kalemine fatura/dekont eki), TownSq/Condo Control (rol bazlı belge).
- **Anket / oylama sonuçları**: Waysim (kilitle-raporla), Edge (oy sonucu kayıt), TownSq (canlı ilerleme).
- **Denetçi görünümü**: e-Yönetim (denetçi şifresi), Odyosi (denetçi paneli), Yönetişim (denetçi rolü).

---

## 7. Öne çıkan "premium" özellikler (kopyalanmaya değer)

1. **Otomatik banka mutabakatı** – ekstre içe aktarma veya API; açıklamadaki ad/daire/10 haneli kod ile otomatik eşleştirme, onayla/sil kuyruğu; eşleşince otomatik makbuz + bildirim (Apsiyon, Konsiyon "0 maliyet", Net Yönetim EHO, Hursoft, Sabilgi "banka bakiye kontrolü", Kendin Yönet). Buildium'da da temel özellik.
2. **Her sakine benzersiz ödeme referans kodu** (Apsiyon 10 haneli kod) – havale açıklamasına yazdırılarak eşleşme oranını yükseltir.
3. **Toplu SMS / e-posta / push + WhatsApp bildirimi ve durum raporu** (Apsiyon WhatsApp Durum Raporu, Net Yönetim, Konsiyon), **IVR sesli arama hatırlatma** (Odyosi), okundu bilgisi (Edge), ücretsiz sistem SMS'i (BYönetim).
4. **QR kodlu makbuz** ve ödeme linki/QR ile borç sayfası (SiteApp, Aidatium); e-makbuzun doğrulanabilir olması.
5. **Borç yaşlandırma ve icra hazırlığı** – yaşlandırma raporu (Edge), İcra Takip modülü ile avukata devir/yetki (Dijiyon, Konsiyon, Apsiyon), md.37 kesinleşmiş işletme projesinin İİK 68 belge niteliği için proje-tebliğ kaydı.
6. **Çoklu site / yönetim şirketi paneli** – her site ayrı muhasebe, tek merkezi panel (APYS, Aidatım Pro, Yönetişim "inşaat ve yönetim firmaları", Dijiyon).
7. **Yönetici devir-teslim paketi** – tek tıkla: karar defteri dökümü, kasa/banka bakiye, dönemsel bakiye listesi, işletme defteri, borçlu listesi, demirbaş envanteri, personel özlük listesi (rehberlerdeki belge listesi; ürünlerde "tüm geçmiş veri tek panelde kalır" vurgusu – https://www.sehiryonetim.com/blog/... , https://gozdeyonetim.com/apartman-yonetimi-devir-teslim-tutanagi-ornegi/).
8. **Denetçi görünümü** – salt okunur rol, 3 aylık denetim dönemi seçimi, 8 bölümlü denetim raporu şablonu, denetim defteri çıktısı (Odyosi, e-Yönetim, İmer şablonu).
9. **İşletme projesi sihirbazı** – geçen yıl giderlerinden tahmin, kalem bazlı dağıtım anahtarı (eşit/arsa payı), daire başı aylık avans, tebliğ ve 7 gün itiraz takibi, tek tıkla tahakkuka dönüştürme (Yönetişim, Dijiyon, Hursoft).
10. **Malik/kiracı ayrı hesap ve tarih bazlı sorumluluk** (Yönetişim, Blok Görevlisi, Apsiyon çıkış bakiye işlemleri) – demirbaş/yenileme malike, işletme gideri kiracıya.
11. **Isı/su gider paylaşımı sayaç endeksiyle** (Sabilgi, Apsiyon, YÖNETİCİ).
12. **FIFO mahsup ve kısmi ödeme kuralı** (SiteApp "TBK uyumlu FIFO"), fazla ödeme/devir/düzeltme izleri (Aidatım).
13. **Sanal POS / kartla online tahsilat** – hemen tüm ürünlerde (Iyzico – SiteBYS; banka POS – Konsiyon; Apsiyon Pos; Odyosi 3D Secure taksit). **Bizim kapsamımızda uygulanmayacak; sadece not.**
14. **Online oy / anket** (Konsiyon, Waysim, BYönetim, Net Yönetim; TownSq yasal bağlayıcı oylama) – md.30 yeter sayısı takibi ve hazirun listesi (Ay) ile birlikte düşünülebilir.
15. **Ziyaretçi / araç-plaka kaydı ve kargo takibi** (Apsiyon, Konsiyon, SiteApp LPR, Sabilgi; Condo Control visitor parking & package).
16. **Ortak alan rezervasyonu** kurallı (Condo Control: blackout, tampon süre, kapasite, şart imzası; Apsiyon, Konsiyon, Aidat.ai).
17. **Belge/karar arşivi ve dijital karar defteri** (Konsiyon, BYönetim, YÖNETİCİ, Hursoft "Toplantı Karar Defteri" raporu).
18. **Yapay zekâ destekli ön muhasebe** – ödeyeni bulma, hesap kodu önerme, anomali/fatura tutarsızlığı uyarısı (Odyosi, Aidat.ai, SiteApp belge okuma).
19. **Özelleştirilebilir makbuz/rapor şablonları** (Apsiyon Rapor Ayarları), Excel/PDF dışa aktarım ve e-posta ile rapor gönderimi (Yönetimcell).
20. **Güvenlik**: 2FA, IP kısıtı, audit log (Apsiyon); rol bazlı personel yetkisi (Konsiyon, Aidatium); sık yedekleme (Konsiyon 10 dk).
21. **KVKK uyumlu şeffaflık**: kişisel borç listesi yerine anonim toplu tahsilat göstergeleri + sakine özel bildirim (2026/348 kararı).

---

### Kısa özet / öncelik önerisi (araştırma bulgularından)
Türk pazarında "asgari beklenen çekirdek": Daireler + Kişiler (malik/kiracı ayrımı) → Borç türleri (aidat/yakıt/su/demirbaş/ek/gecikme) ve dağıtım anahtarı (eşit/arsa payı/m²/tip) → Toplu tahakkuk → Tahsilat (nakit/havale/kart) + seri numaralı makbuz → Gider (kategori+belge) → Kasa/Banka → Raporlar (borçlu listesi, daire ekstresi, gelir-gider, kasa/banka, işletme defteri, işletme projesi, denetim raporu) → Duyuru/SMS → Sakin portalı (kendi borcu + yönetimin paylaştığı raporlar). Farklılaştırıcılar: banka mutabakatı, denetçi rolü, devir-teslim paketi, KVKK-uyumlu şeffaflık, karar defteri/oylama.
