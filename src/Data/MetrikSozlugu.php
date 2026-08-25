<?php

declare(strict_types=1);

namespace Bist\Data;

/**
 * Metrik anahtarlarinin Turkce adi, birimi ve "ne demek" satiri (G-13).
 *
 * Aciklamalar jargonsuz ve tek cumledir; proje talimatinin
 * "Her rakamin yaninda 'ne demek' satiri" kuralini karsilar.
 */
final class MetrikSozlugu
{
    /** @var array<string, array{ad: string, birim: string, neDemek: string}> */
    private const TANIMLAR = [
        'net_satis' => [
            'ad' => 'Net satış',
            'birim' => 'TL',
            'neDemek' => 'Şirketin bir dönemde sattığı mal ve hizmetin, iade ve indirimler düşülmüş toplam tutarı.',
        ],
        'favok' => [
            'ad' => 'FAVÖK',
            'birim' => 'TL',
            'neDemek' => 'Faiz, amortisman ve vergi düşülmeden önceki faaliyet kârı; işin kendisi para kazanıyor mu sorusuna bakar.',
        ],
        'favok_marji' => [
            'ad' => 'FAVÖK marjı',
            'birim' => '%',
            'neDemek' => 'Her 100 TL satıştan geriye kalan faaliyet kârı.',
        ],
        'net_kar' => [
            'ad' => 'Net kâr',
            'birim' => 'TL',
            'neDemek' => 'Tüm gider, faiz ve vergi düşüldükten sonra kalan tutar.',
        ],
        'hisse_basina_kar' => [
            'ad' => 'Hisse başına kâr',
            'birim' => 'TL',
            'neDemek' => 'Net kârın hisse sayısına bölünmüş hali; bir hisseye düşen kâr.',
        ],
        'fiyat' => [
            'ad' => 'Fiyat',
            'birim' => 'TL',
            'neDemek' => 'Hissenin piyasadaki son işlem fiyatı.',
        ],
        'fk' => [
            'ad' => 'F/K',
            'birim' => 'x',
            'neDemek' => 'Fiyatın hisse başına kâra oranı; bugünkü kâr sabit kalsa yatırımın kaç yılda döneceğini gösterir.',
        ],
        'ozkaynak' => [
            'ad' => 'Özkaynak',
            'birim' => 'TL',
            'neDemek' => 'Varlıklardan borçlar düşüldükten sonra ortaklara kalan tutar.',
        ],
        'net_borc' => [
            'ad' => 'Net borç',
            'birim' => 'TL',
            'neDemek' => 'Finansal borçlardan nakit ve benzerleri düşülmüş tutar.',
        ],
        'net_borc_favok' => [
            'ad' => 'Net borç/FAVÖK',
            'birim' => 'x',
            'neDemek' => 'Şirket mevcut faaliyet kârıyla borcunu kaç yılda kapatır.',
        ],
        'piyasa_degeri' => [
            'ad' => 'Piyasa değeri',
            'birim' => 'TL',
            'neDemek' => 'Hisse fiyatı ile toplam hisse sayısının çarpımı; piyasanın şirkete biçtiği değer.',
        ],
        'gunluk_hacim' => [
            'ad' => 'Günlük ortalama işlem hacmi',
            'birim' => 'TL',
            'neDemek' => 'Günde ortalama ne kadarlık hisse el değiştiriyor; düşükse alım satım zorlaşır.',
        ],
    ];

    /** @return array{ad: string, birim: string, neDemek: string} */
    public static function tanim(string $anahtar): array
    {
        return self::TANIMLAR[$anahtar] ?? [
            'ad' => $anahtar,
            'birim' => '',
            'neDemek' => 'Bu metrik için tanım kayıtlı değil.',
        ];
    }

    /** @return list<string> */
    public static function anahtarlar(): array
    {
        return array_keys(self::TANIMLAR);
    }
}
