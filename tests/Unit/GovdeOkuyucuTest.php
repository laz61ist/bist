<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Http\GovdeHatasi;
use Bist\Http\GovdeOkuyucu;
use PHPUnit\Framework\TestCase;

/**
 * D9 (#13): Girdi boyutu sinirsizdi.
 *
 * Olculen sömürü: 200.000 kriterlik 12 MB govde -> 201 Created, 5,13 sn CPU.
 * post_max_size = 8M ayarli olmasina ragmen kabul edildi; PHP bu limiti
 * application/json govdelerine UYGULAMIYOR, tek fren memory_limit.
 */
final class GovdeOkuyucuTest extends TestCase
{
    private const SINIR = 65536; // 64 KB

    private function okuyucu(string $govde, ?int $contentLength = null): GovdeOkuyucu
    {
        return new GovdeOkuyucu(
            azamiBayt: self::SINIR,
            azamiDerinlik: 8,
            contentLength: $contentLength,
            govdeSaglayici: static fn (): string => $govde,
        );
    }

    // --- Content-Length kapisi: govdeyi OKUMADAN reddet ---

    public function testContentLengthSiniriAsiyorsaGovdeHicOkunmaz(): void
    {
        $okundu = false;
        $o = new GovdeOkuyucu(
            azamiBayt: self::SINIR,
            azamiDerinlik: 8,
            contentLength: self::SINIR + 1,
            govdeSaglayici: static function () use (&$okundu): string {
                $okundu = true;
                return '{}';
            },
        );

        try {
            $o->json();
            self::fail('GovdeHatasi bekleniyordu');
        } catch (GovdeHatasi $e) {
            self::assertSame(413, $e->durumKodu);
        }

        self::assertFalse($okundu, 'Gövde okunmamalıydı; Content-Length zaten sınırı aşıyor');
    }

    public function testContentLengthSinirdaysaKabulEdilir(): void
    {
        $o = $this->okuyucu('{"a":1}', self::SINIR);

        self::assertSame(['a' => 1], $o->json());
    }

    // --- Gercek boyut kapisi: Content-Length yalan soyleyebilir ---

    public function testContentLengthYalanSoyluyorsaGercekBoyutYakalanir(): void
    {
        $buyuk = str_repeat('x', self::SINIR + 100);
        $o = $this->okuyucu('{"a":"' . $buyuk . '"}', contentLength: 10);

        $this->expectException(GovdeHatasi::class);
        $this->expectExceptionCode(413);

        $o->json();
    }

    public function testContentLengthYoksaDaGercekBoyutDenetlenir(): void
    {
        $buyuk = str_repeat('x', self::SINIR + 100);
        $o = $this->okuyucu('{"a":"' . $buyuk . '"}', contentLength: null);

        $this->expectException(GovdeHatasi::class);
        $o->json();
    }

    // --- Derinlik kapisi ---

    public function testCokDerinIcIceJsonReddedilir(): void
    {
        $derin = str_repeat('[', 20) . '1' . str_repeat(']', 20);
        $o = $this->okuyucu($derin);

        $this->expectException(GovdeHatasi::class);
        $this->expectExceptionCode(422);

        $o->json();
    }

    public function testSinirdakiDerinlikKabulEdilir(): void
    {
        // azamiDerinlik 8 -> 7 seviye ic ice gecerli olmali
        $o = $this->okuyucu('[[[[[[[1]]]]]]]');

        self::assertIsArray($o->json());
    }

    // --- Bicimsiz JSON ---

    public function testBozukJson422Doner(): void
    {
        $o = $this->okuyucu('{bozuk');

        $this->expectException(GovdeHatasi::class);
        $this->expectExceptionCode(422);

        $o->json();
    }

    public function testBosGovdeBosDiziDoner(): void
    {
        self::assertSame([], $this->okuyucu('')->json());
    }

    public function testUstDuzeySkalerReddedilir(): void
    {
        // "5" gecerli JSON ama gövde bir nesne veya dizi olmali
        $o = $this->okuyucu('5');

        $this->expectException(GovdeHatasi::class);
        $this->expectExceptionCode(422);

        $o->json();
    }

    // --- hata mesaji sizinti yapmamali ---

    public function testHataMesajiIcYapiSizdirmaz(): void
    {
        $o = $this->okuyucu('{bozuk');

        try {
            $o->json();
            self::fail('GovdeHatasi bekleniyordu');
        } catch (GovdeHatasi $e) {
            self::assertStringNotContainsString('/home/', $e->getMessage());
            self::assertStringNotContainsString('Bist\\', $e->getMessage());
            self::assertNotSame('', trim($e->getMessage()));
        }
    }
}
