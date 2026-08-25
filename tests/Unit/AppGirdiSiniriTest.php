<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\App;
use Bist\Data\BosKaynak;
use Bist\Data\KriterDeposu;
use PHPUnit\Framework\TestCase;

/**
 * D9 (#13) ikinci yari: alan bazli sinirlar.
 *
 * Govde boyutu sinirlansa bile 64 KB icine 50.000 kisa kriter sigar.
 * Bu yuzden alan sayisi ve uzunlugu ayrica denetlenir.
 */
final class AppGirdiSiniriTest extends TestCase
{
    private string $db;

    protected function setUp(): void
    {
        $this->db = tempnam(sys_get_temp_dir(), 'bist-sinir-') ?: '';
        @unlink($this->db);
    }

    protected function tearDown(): void
    {
        @unlink($this->db);
    }

    private function app(): App
    {
        return new App(new BosKaynak(), new KriterDeposu($this->db));
    }

    /** @param array<mixed> $post */
    private function kaydet(array $post): array
    {
        return $this->app()->calistir('/kriter', 'POST', [], $post);
    }

    private function kriter(string $ad = 'F/K'): array
    {
        return ['ad' => $ad, 'anahtar' => 'fk', 'operator' => '<', 'esik' => 12];
    }

    public function testGecerliKayitHalaKabulEdilir(): void
    {
        $c = $this->kaydet(['ad' => 'Set', 'kriterler' => [$this->kriter()]]);

        self::assertSame(201, $c['durum']);
    }

    public function testCokFazlaKriterReddedilir(): void
    {
        $kriterler = array_fill(0, App::AZAMI_KRITER + 1, $this->kriter());

        $c = $this->kaydet(['ad' => 'Set', 'kriterler' => $kriterler]);

        self::assertSame(422, $c['durum']);
        self::assertStringContainsString('kriter', mb_strtolower((string) $c['govde'], 'UTF-8'));
    }

    public function testSinirdakiKriterSayisiKabulEdilir(): void
    {
        $kriterler = array_fill(0, App::AZAMI_KRITER, $this->kriter());

        self::assertSame(201, $this->kaydet(['ad' => 'Set', 'kriterler' => $kriterler])['durum']);
    }

    public function testCokUzunAdReddedilir(): void
    {
        $c = $this->kaydet([
            'ad' => str_repeat('a', App::AZAMI_AD + 1),
            'kriterler' => [$this->kriter()],
        ]);

        self::assertSame(422, $c['durum']);
    }

    public function testCokUzunGerekceReddedilir(): void
    {
        $c = $this->kaydet([
            'ad' => 'Set',
            'gerekce' => str_repeat('a', App::AZAMI_GEREKCE + 1),
            'kriterler' => [$this->kriter()],
        ]);

        self::assertSame(422, $c['durum']);
    }

    public function testCokUzunKriterAdiReddedilir(): void
    {
        $c = $this->kaydet([
            'ad' => 'Set',
            'kriterler' => [$this->kriter(str_repeat('a', App::AZAMI_AD + 1))],
        ]);

        self::assertSame(422, $c['durum']);
    }

    public function testUzunlukBaytaDegilKarakteraGoreOlculur(): void
    {
        // Turkce karakterler UTF-8'de 2 bayt; sinir karakter cinsinden olmali
        $ad = str_repeat('ş', App::AZAMI_AD);

        self::assertSame(201, $this->kaydet(['ad' => $ad, 'kriterler' => [$this->kriter()]])['durum']);
    }

    // --- S4 (#15): istisna mesaji ic yapiyi sizdirmamali ---

    public function testGecersizOperatorIcNamespaceSizdirmaz(): void
    {
        $c = $this->kaydet([
            'ad' => 'Set',
            'kriterler' => [['ad' => 'F/K', 'anahtar' => 'fk', 'operator' => 'DROP', 'esik' => 1]],
        ]);

        self::assertSame(422, $c['durum']);
        self::assertStringNotContainsString('Bist\\', (string) $c['govde']);
        self::assertStringNotContainsString('enum', mb_strtolower((string) $c['govde'], 'UTF-8'));
        // kullaniciya ise yarar bilgi vermeli
        self::assertStringContainsString('operator', mb_strtolower((string) $c['govde'], 'UTF-8'));
    }

    public function testGecersizOperatorKabulEdilenleriListeler(): void
    {
        $c = $this->kaydet([
            'ad' => 'Set',
            'kriterler' => [['ad' => 'F/K', 'anahtar' => 'fk', 'operator' => 'DROP', 'esik' => 1]],
        ]);

        $govde = (string) $c['govde'];
        foreach (['<', '<=', '>', '>='] as $op) {
            self::assertStringContainsString($op, $govde);
        }
    }
}
