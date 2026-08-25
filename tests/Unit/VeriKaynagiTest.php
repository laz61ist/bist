<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Data\BosKaynak;
use Bist\Data\VeriKaynagi;
use Bist\Domain\Etiket;
use PHPUnit\Framework\TestCase;

/**
 * G-16: Veri kaynagi degistirilebilir olmali; arayuz uzerinden soyutlanir.
 * G-15: Temsili veri uretilmez. Kaynak bagli degilse her metrik bos doner.
 */
final class VeriKaynagiTest extends TestCase
{
    public function testBosKaynakVeriKaynagiArayuzunuUygular(): void
    {
        self::assertInstanceOf(VeriKaynagi::class, new BosKaynak());
    }

    public function testBosKaynakBagliDegildir(): void
    {
        self::assertFalse((new BosKaynak())->bagli());
        self::assertSame('bağlı kaynak yok', (new BosKaynak())->ad());
    }

    public function testBosKaynakHerMetrigiBosDondururVeNedeniniYazar(): void
    {
        $k = new BosKaynak();
        $m = $k->metrik('TTRAK', 'net_satis');

        self::assertFalse($m->vardir());
        self::assertSame('—', $m->gorunenDeger());
        self::assertContains(Etiket::KAYNAKSIZ, $m->etiketler());
        self::assertStringContainsString('bağlanmadı', (string) $m->eksikNedeni());
    }

    public function testBosKaynakBilinenMetrikIcinAdVeAciklamaTasir(): void
    {
        $m = (new BosKaynak())->metrik('TTRAK', 'net_satis');

        self::assertSame('Net satış', $m->ad);
        self::assertNotSame('', trim($m->neDemek));
    }

    public function testBilinmeyenAnahtarDaBosMetrikUretir(): void
    {
        $m = (new BosKaynak())->metrik('TTRAK', 'olmayan_metrik');

        self::assertFalse($m->vardir());
        self::assertSame('olmayan_metrik', $m->ad);
    }

    public function testBosKaynakAsiaUydurmaDegerUretmez(): void
    {
        $k = new BosKaynak();

        foreach (['net_satis', 'favok', 'fk', 'ozkaynak', 'net_borc'] as $anahtar) {
            self::assertFalse($k->metrik('TTRAK', $anahtar)->vardir(), "{$anahtar} için değer üretildi");
        }
    }
}
