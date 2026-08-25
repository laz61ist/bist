<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Config\Ayarlar;
use Bist\Http\Yetki;
use PHPUnit\Framework\TestCase;

/**
 * D6 (#10): POST /kriter hicbir oturum, token, CSRF veya hiz siniri
 * olmadan sqlite'a yaziyordu. GET /kriter tum kayitlari kimlik sormadan
 * listeliyordu.
 *
 * Karar A2: paylasimli token — AMA token yapilandirilmamissa yazma KAPALI.
 * Bu, Ortam::PRODUCTION varsayilaniyla ayni mantik: yapilandirma eksikse
 * sistem en kisitli moda duser, en gevsegine degil.
 */
final class YetkiTest extends TestCase
{
    private const TOKEN = 'gizli-token-en-az-yirmi-karakter';

    private function yetki(?string $token): Yetki
    {
        return new Yetki($token);
    }

    // --- Guvenli varsayilan: token yoksa yazma KAPALI ---

    public function testTokenYapilandirilmamissaYazmaKapalidir(): void
    {
        self::assertFalse($this->yetki(null)->yazmaAcik());
    }

    public function testBosTokenYapilandirilmamisSayilir(): void
    {
        self::assertFalse($this->yetki('   ')->yazmaAcik());
    }

    public function testTokenVarsaYazmaAciktir(): void
    {
        self::assertTrue($this->yetki(self::TOKEN)->yazmaAcik());
    }

    public function testYazmaKapaliykenHicbirTokenKabulEdilmez(): void
    {
        $y = $this->yetki(null);

        self::assertFalse($y->dogrula('herhangi-bir-token'));
        self::assertFalse($y->dogrula(''));
        self::assertFalse($y->dogrula(null));
    }

    // --- Dogrulama ---

    public function testDogruTokenKabulEdilir(): void
    {
        self::assertTrue($this->yetki(self::TOKEN)->dogrula(self::TOKEN));
    }

    public function testYanlisTokenReddedilir(): void
    {
        self::assertFalse($this->yetki(self::TOKEN)->dogrula('yanlis'));
    }

    public function testTokensizIstekReddedilir(): void
    {
        self::assertFalse($this->yetki(self::TOKEN)->dogrula(null));
    }

    public function testTokenTamEslesmeliOnEkYetmez(): void
    {
        self::assertFalse($this->yetki(self::TOKEN)->dogrula(substr(self::TOKEN, 0, 10)));
        self::assertFalse($this->yetki(self::TOKEN)->dogrula(self::TOKEN . 'ekstra'));
    }

    // --- Kisa token reddedilmeli: zayif sir guvenlik yanilsamasi uretir ---

    public function testCokKisaTokenYapilandirmasiReddedilir(): void
    {
        self::assertFalse($this->yetki('kisa')->yazmaAcik());
        self::assertFalse($this->yetki(str_repeat('a', Yetki::ASGARI_UZUNLUK - 1))->yazmaAcik());
        self::assertTrue($this->yetki(str_repeat('a', Yetki::ASGARI_UZUNLUK))->yazmaAcik());
    }

    // --- Basliktan cikarma ---

    public function testBearerBasligindanTokenCikarilir(): void
    {
        self::assertSame('abc', Yetki::baslikta(['HTTP_AUTHORIZATION' => 'Bearer abc']));
    }

    public function testBearerBuyukKucukHarfDuyarsizdir(): void
    {
        self::assertSame('abc', Yetki::baslikta(['HTTP_AUTHORIZATION' => 'bearer abc']));
    }

    public function testBasliksizIstekNullDoner(): void
    {
        self::assertNull(Yetki::baslikta([]));
        self::assertNull(Yetki::baslikta(['HTTP_AUTHORIZATION' => 'Basic abc']));
        self::assertNull(Yetki::baslikta(['HTTP_AUTHORIZATION' => 'Bearer']));
    }

    /**
     * Apache 2.4, Authorization basligini CGI/alt-surec ortamina
     * AKTARMAZ (util_script.c, ap_add_common_vars): CGIPassAuth On
     * verilmedikce baslik dusurulur. Bazi kurulumlar bunu
     *   SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
     * ile asar; FallbackResource/mod_rewrite ic yonlendirmesi devredeyse
     * degisken PHP'ye REDIRECT_ onekiyle ulasir.
     *
     * Bu, uydurma bir senaryo degil: CI #9'da konteyner dogru tokenla
     * 401 dondu, cunku php -S basligi geciriyor, Apache gecirmiyordu.
     */
    public function testApacheRedirectOnekliBaslikDaOkunur(): void
    {
        self::assertSame('abc', Yetki::baslikta(['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer abc']));
    }

    public function testDogrudanBaslikRedirectOneklininOnundedir(): void
    {
        self::assertSame('gercek', Yetki::baslikta([
            'HTTP_AUTHORIZATION' => 'Bearer gercek',
            'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer eski',
        ]));
    }

    // --- Ayarlar entegrasyonu ---

    public function testTokenOrtamDegiskenindenOkunur(): void
    {
        $a = Ayarlar::ortamdan(['BIST_YAZMA_TOKEN' => self::TOKEN], '/uygulama');

        self::assertSame(self::TOKEN, $a->yazmaToken);
    }

    public function testTokenYoksaNullKalir(): void
    {
        self::assertNull(Ayarlar::ortamdan([], '/uygulama')->yazmaToken);
    }
}
