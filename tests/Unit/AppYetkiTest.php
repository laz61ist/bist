<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\App;
use Bist\Data\BosKaynak;
use Bist\Data\KriterDeposu;
use Bist\Http\Yetki;
use PHPUnit\Framework\TestCase;

/** D6 (#10) — App seviyesinde erisim denetimi. */
final class AppYetkiTest extends TestCase
{
    private const TOKEN = 'gizli-token-en-az-yirmi-karakter';

    private function app(?string $token): App
    {
        return new App(
            kaynak: new BosKaynak(),
            depo: new KriterDeposu(':memory:'),
            yetki: new Yetki($token),
        );
    }

    private function kriterler(): array
    {
        return [['ad' => 'F/K', 'anahtar' => 'fk', 'operator' => '<', 'esik' => 12]];
    }

    // --- Token yapilandirilmamis: yazma KAPALI ---

    public function testTokensizYapilandirmadaYazma403Doner(): void
    {
        $c = $this->app(null)->calistir('/kriter', 'POST', [], [
            'ad' => 'Set', 'kriterler' => $this->kriterler(),
        ]);

        self::assertSame(403, $c['durum']);
        self::assertStringContainsString('salt okunur', mb_strtolower((string) $c['govde'], 'UTF-8'));
    }

    public function testTokensizYapilandirmadaOkumaDa403Doner(): void
    {
        // gerekce alani kullanicinin kendi yatirim niyeti; anonim okunmamali
        self::assertSame(403, $this->app(null)->calistir('/kriter', 'GET')['durum']);
    }

    public function testTokensizYapilandirmadaKokpitAcikKalir(): void
    {
        // Pano salt okunur yayinlanabilmeli
        self::assertSame(200, $this->app(null)->calistir('/')['durum']);
    }

    public function testTokensizYapilandirmadaSaglikAcikKalir(): void
    {
        self::assertSame(200, $this->app(null)->calistir('/saglik')['durum']);
    }

    // --- Token yapilandirilmis: dogrulama gerekli ---

    public function testTokenlisistemdeYetkisizYazma401Doner(): void
    {
        $c = $this->app(self::TOKEN)->calistir('/kriter', 'POST', [], [
            'ad' => 'Set', 'kriterler' => $this->kriterler(),
        ]);

        self::assertSame(401, $c['durum']);
        self::assertSame('Bearer', $c['basliklar']['WWW-Authenticate'] ?? null);
    }

    public function testYanlisTokenla401Doner(): void
    {
        $c = $this->app(self::TOKEN)->calistir('/kriter', 'POST', [], [
            'ad' => 'Set', 'kriterler' => $this->kriterler(),
        ], sunulanToken: 'yanlis-token-yirmi-karakterden-uzun');

        self::assertSame(401, $c['durum']);
    }

    public function testDogruTokenlaYazmaCalisir(): void
    {
        $c = $this->app(self::TOKEN)->calistir('/kriter', 'POST', [], [
            'ad' => 'Set', 'kriterler' => $this->kriterler(),
        ], sunulanToken: self::TOKEN);

        self::assertSame(201, $c['durum']);
    }

    public function testDogruTokenlaOkumaCalisir(): void
    {
        self::assertSame(200, $this->app(self::TOKEN)
            ->calistir('/kriter', 'GET', sunulanToken: self::TOKEN)['durum']);
    }

    public function testYetkisizOkuma401Doner(): void
    {
        self::assertSame(401, $this->app(self::TOKEN)->calistir('/kriter', 'GET')['durum']);
    }

    // --- Yanit sizinti yapmamali ---

    public function testYetkiYanitiTokenSizdirmaz(): void
    {
        $c = $this->app(self::TOKEN)->calistir('/kriter', 'GET');

        self::assertStringNotContainsString(self::TOKEN, (string) $c['govde']);
    }

    public function testYetkiYanitiOnbelleklenmez(): void
    {
        $c = $this->app(self::TOKEN)->calistir('/kriter', 'GET');

        self::assertSame('no-store', $c['basliklar']['Cache-Control'] ?? null);
    }

    // --- Kriter listesi de onbelleklenmemeli (#15/S1 ile ayni gerekce) ---

    public function testKriterListesiOnbelleklenmez(): void
    {
        $c = $this->app(self::TOKEN)->calistir('/kriter', 'GET', sunulanToken: self::TOKEN);

        self::assertSame('no-store', $c['basliklar']['Cache-Control'] ?? null);
    }
}
