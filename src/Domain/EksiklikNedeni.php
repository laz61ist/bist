<?php

declare(strict_types=1);

namespace Bist\Domain;

/**
 * Bir metrigin neden bos oldugunun TIPI.
 *
 * Gerekce (abstention literaturu, bkz. docs/arastirma/): tek tip
 * "bilmiyorum" bilgi kaybidir. Farkli nedenler kullanicidan farkli
 * eylem ister; tip, arayuzun dogru eylemi onerebilmesini saglar.
 */
enum EksiklikNedeni
{
    /** Hicbir kaynakta bulunamadi. */
    case KAYNAK_YOK;

    /** Birden fazla kaynak farkli deger veriyor. */
    case KAYNAK_CELISKILI;

    /** Veri var ama istenen donem icin degil. */
    case DONEM_UYUMSUZ;

    /** Sayi var ama birimi veya olcegi cozulemedi. */
    case OLCEK_BELIRSIZ;

    /** Hesap tanimsiz (payda sifir veya negatif, isaret degisimi). */
    case HESAPLANAMAZ;

    /** Donemler farkli olcu biriminde; TMS 29 karsilastirma kapisi kapali. */
    case KARSILASTIRILAMAZ;

    public function metin(): string
    {
        return match ($this) {
            self::KAYNAK_YOK => 'Kaynakta bulunamadı',
            self::KAYNAK_CELISKILI => 'Kaynaklar çelişiyor',
            self::DONEM_UYUMSUZ => 'İstenen dönem için veri yok',
            self::OLCEK_BELIRSIZ => 'Birim veya ölçek çözülemedi',
            self::HESAPLANAMAZ => 'Hesap tanımsız',
            self::KARSILASTIRILAMAZ => 'Dönemler karşılaştırılamaz',
        };
    }

    /** Kullanicinin bu durumda yapabilecegi somut is. */
    public function eylem(): string
    {
        return match ($this) {
            self::KAYNAK_YOK => 'Veri kaynağını bağla veya KAP dosyasını yükle.',
            self::KAYNAK_CELISKILI => 'Hangi kaynağın esas alınacağını seç.',
            self::DONEM_UYUMSUZ => 'Başka bir dönem seç veya o dönemin raporunu ekle.',
            self::OLCEK_BELIRSIZ => 'Kaynak raporun ölçü birimini (bin/milyon TL) belirt.',
            self::HESAPLANAMAZ => 'Girdi kalemlerini kontrol et; bu oran bu veriyle tanımsız.',
            self::KARSILASTIRILAMAZ => 'İki dönemin TMS 29 düzeltme durumunu KAP dipnotundan teyit et.',
        };
    }
}
