# BIST Kokpit Anayasası

**Sürüm:** 1.0.0 · **Yürürlük:** 2026-08-25 · **Son değişiklik:** 2026-08-25

Bu belge projenin pazarlık edilemez kurallarını tanımlar. Çelişki halinde
anayasa kazanır. Kural eklemek için commit gerekir; sözlü mutabakat yetmez.

## I. Kaynaksız rakam yoktur (PAZARLIK EDİLEMEZ)

Kaynağı ve dönemi olmayan hiçbir sayı üretilmez, gösterilmez, saklanmaz.
Bu bir doğrulama adımı değil, **tip sistemi kısıtıdır**: `Metrik` yapıcısı
kaynaksız veya dönemsiz değer verildiğinde istisna atar. Nesne kurulamaz.

Bulunamayan veri `—` ile gösterilir, altında nedeni ve kullanıcının
yapabileceği eylem yazar. Rapor doldurmak için sayı üretilmez.

**İhlal testi:** `MetrikTest::testDegerVarkenKaynakYoksaMetrikKurulamaz`

## II. Yatırım tavsiyesi üretilmez (PAZARLIK EDİLEMEZ)

Al, sat, tut, hedef fiyat yok. "Ucuz", "pahalı", "cazip", "fırsat",
"en iyi" gibi niteleyici sıfat yok. Sıralanmış "en iyi N" listesi yok.
Sistem konum bildirir, hüküm vermez.

Türkiye'de yatırım danışmanlığı SPK izni gerektirir. Bu kural hukuki
zorunluluktur, üslup tercihi değil.

Sistem kullanıcının kişisel finansal koşullarını (gelir, risk toleransı,
yaş, vade) **toplamaz**. Toplamamak bir gizlilik tercihi değil, uyum
kontrolüdür: toplanan veri, kişiselleştirilmiş tavsiye eşiğine yaklaştırır.

**İhlal testi:** `KriterTaramaTest::testOzetTavsiyeIfadesiIcermez`,
E2E adım 8

## III. Eşiği kullanıcı koyar

Sistem kendi eşiğini üretmez, önermez, varsayılan koymaz. Eşik yoksa
tarama çalışmaz. Kullanıcının eşiğiyle birlikte **kendi gerekçesi** de
saklanır; çıktının sahibi kullanıcıdır.

**İhlal testi:** `KriterTaramaTest::testBosKriterSetiKurulamaz`

## IV. Hesaplayamıyorsan hesaplama

Şüpheli girdide sayı üretmek, sayı üretmemekten kötüdür. Yanlış sayı
makul görünür ve yanlış olduğu çıktıdan anlaşılmaz.

Kapalı kapılar:
- İki dönemin TMS 29 düzeltme durumu farklıysa büyüme oranı hesaplanmaz
- Değer işaret değiştirdiyse yüzde değişim üretilmez, mutlak fark döner
- Payda sıfır veya negatifse oran tanımsızdır
- Veri eksikse "geçti/kaldı" denmez, "değerlendirilemedi" denir

Boş bırakma **tiplidir**; her tipin ayrı kullanıcı eylemi vardır.

**İhlal testi:** `Tms29KontrolTest`, `OranTest`

## V. Test önce yazılır (PAZARLIK EDİLEMEZ)

Üretim kodu, kendisini gerektiren başarısız bir test olmadan yazılmaz.
Kırmızıyı görmeden yeşile geçilmez. Test hata (error) değil başarısızlık
(failure) vermelidir; hata alınırsa önce hata giderilir.

"Bu sefer atlayayım" düşüncesi ihlaldir. Kod önce yazıldıysa silinir,
testten yeniden yazılır.

## VI. Kural uydurulmaz, kaynağından okunur

Alan kuralları (TMS 29 karar tablosu, tarama disiplini, etiket sistemi)
tahminle yazılmaz. `.claude/skills/` altındaki skill dosyalarından ve
`docs/REHBER-SAYFA-HARITASI.md`'deki numaralı gereksinimlerden okunur.
Her sınıf hangi gereksinimi taşıdığını doc-block'unda yazar.

## VII. Doğrulanmamış bilgi işaretlenir

Kaynak, DOI, madde numarası, sürüm, fiyat uydurulmaz. Emin olunmayan
her şey `[DOĞRULANMASI GEREKİYOR]` taşır. Özet okumak tam okumak
sayılmaz. Erişilemeyen kaynak "erişilemedi" diye raporlanır.

## VIII. Mevcut çalışan koda dokunulmaz

Yeni eklenen şey mevcut isimlendirme ve katman organizasyonuna uyar.
Teorik olarak daha iyi bir çözüm varsa bile mevcut kalıp korunur.
Refactor ancak testler yeşilken ve davranış değişmeden yapılır.

## IX. Sır repoya girmez

API anahtarı, veritabanı şifresi, token, webhook adresi koda, teste,
workflow'a veya commit mesajına yazılmaz. Yapılandırma ortam
değişkeninden okunur; kod değeri bilmez.

## X. Kanıtsız "tamam" denmez

Bir işin bittiği, testin geçtiği veya hatanın düzeldiği, o oturumda
alınmış bir komut çıktısına dayanmadan bildirilmez. Test düştüyse
çıktısı gösterilir. Atlanan adım varsa açıkça söylenir.

## Yönetişim

- Anayasa değişikliği commit gerektirir; mesajda gerekçe bulunur
- Davranış bozan değişiklik `BREAKING:` ön ekiyle işaretlenir
- Her PR bu maddelere karşı denetlenir; ihlal varsa gerekçesi PR'da yazılır
- Madde I, II ve V ihlali PR'ı bloke eder
