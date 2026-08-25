<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Domain\EksiklikNedeni;
use Bist\Domain\Metrik;
use PHPUnit\Framework\TestCase;

/**
 * Arastirma bulgusu (abstention literaturu): bos birakma TIPLI olmali,
 * tek tip "bilmiyorum" olmamali. Farkli nedenler farkli kullanici
 * eylemi gerektirir; tek etikete indirmek bilgi kaybidir.
 */
final class EksiklikNedeniTest extends TestCase
{
    public function testTumNedenTipleriTanimlidir(): void
    {
        $adlar = array_map(static fn (EksiklikNedeni $e): string => $e->name, EksiklikNedeni::cases());

        self::assertSame([
            'KAYNAK_YOK',
            'KAYNAK_CELISKILI',
            'DONEM_UYUMSUZ',
            'OLCEK_BELIRSIZ',
            'HESAPLANAMAZ',
            'KARSILASTIRILAMAZ',
        ], $adlar);
    }

    public function testHerNedeninKullaniciyaDonukAciklamasiVardir(): void
    {
        foreach (EksiklikNedeni::cases() as $neden) {
            self::assertNotSame('', trim($neden->metin()), "{$neden->name} için metin yok");
        }
    }

    public function testHerNedeninKullaniciEylemiVardir(): void
    {
        // Farkli neden -> farkli eylem. Ayni eylemi doneniyorsa tip anlamsizdir.
        $eylemler = array_map(
            static fn (EksiklikNedeni $e): string => $e->eylem(),
            EksiklikNedeni::cases(),
        );

        self::assertSame(count($eylemler), count(array_unique($eylemler)));
    }

    public function testMetrikTipliNedenTasiyabilir(): void
    {
        $m = new Metrik(
            ad: 'F/K',
            neDemek: 'Açıklama.',
            eksikNedeni: 'Veri kaynağı bağlanmadı.',
            nedenTipi: EksiklikNedeni::KAYNAK_YOK,
        );

        self::assertSame(EksiklikNedeni::KAYNAK_YOK, $m->nedenTipi());
        self::assertStringContainsString('bağla', $m->nedenTipi()?->eylem() ?? '');
    }

    public function testTipVerilmezseNullKalir(): void
    {
        $m = new Metrik(ad: 'F/K', neDemek: 'Açıklama.', eksikNedeni: 'Bir neden.');

        self::assertNull($m->nedenTipi());
    }

    public function testEtiketlemeSonrasiTipKorunur(): void
    {
        $m = (new Metrik(
            ad: 'F/K',
            neDemek: 'Açıklama.',
            eksikNedeni: 'Ölçek çözülemedi.',
            nedenTipi: EksiklikNedeni::OLCEK_BELIRSIZ,
        ))->etiketle(\Bist\Domain\Etiket::DUSUK_LIKIDITE);

        self::assertSame(EksiklikNedeni::OLCEK_BELIRSIZ, $m->nedenTipi());
    }
}
