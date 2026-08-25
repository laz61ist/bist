<?php

declare(strict_types=1);

namespace Bist\Http;

/**
 * Yazma ucu erisim denetimi.
 *
 * D6 (#10): POST /kriter hicbir oturum, token, CSRF veya hiz siniri
 * olmadan sqlite'a yaziyordu. GET /kriter tum kayitlari kimlik sormadan
 * listeliyordu.
 *
 * Karar A2 — paylasimli token, GUVENLI VARSAYILAN ile:
 * token yapilandirilmamissa yazma KAPALI. Sebep, Ortam::PRODUCTION
 * varsayilaniyla ayni: yapilandirma eksikse sistem en kisitli moda
 * duser, en gevsegine degil. Boylece imaj hicbir ayar verilmeden
 * calistirilirsa pano salt okunur yayinlanir, acik kapi birakmaz.
 *
 * Yazmayi tamamen kaldirmak yerine token secildi cunku anayasa madde III
 * kullanicinin kendi gerekcesiyle esik saklamasini urunun ozu sayiyor.
 */
final readonly class Yetki
{
    /** Bundan kisa sir, guvenlik yanilsamasi uretir; yapilandirilmamis sayilir. */
    public const ASGARI_UZUNLUK = 20;

    private ?string $token;

    public function __construct(?string $token)
    {
        $temiz = trim((string) $token);
        $this->token = strlen($temiz) >= self::ASGARI_UZUNLUK ? $temiz : null;
    }

    /** Yazma ucu kullanilabilir mi. */
    public function yazmaAcik(): bool
    {
        return $this->token !== null;
    }

    /** Sunulan sirri sabit zamanda dogrular. */
    public function dogrula(?string $sunulan): bool
    {
        if ($this->token === null || $sunulan === null) {
            return false;
        }

        return hash_equals($this->token, $sunulan);
    }

    /**
     * Authorization: Bearer <token> basligindan sirri cikarir.
     *
     * REDIRECT_ onekli anahtar da okunur. Apache 2.4 Authorization
     * basligini alt-surec ortamina kendiliginden AKTARMAZ
     * (util_script.c, ap_add_common_vars: CGIPassAuth kapaliysa baslik
     * dusurulur). Kurulumlar bunu ya CGIPassAuth On ile ya da
     *   SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
     * ile asar; ikinci yolda FallbackResource/mod_rewrite ic
     * yonlendirmesi degiskene REDIRECT_ oneki ekler.
     *
     * Guvenlik notu: REDIRECT_HTTP_AUTHORIZATION istemci tarafindan
     * dogrudan doldurulamaz — istemcinin gonderdigi bir baslik
     * $_SERVER'a HTTP_ onekiyle girer, REDIRECT_ onekini yalnizca
     * sunucunun ic yonlendirmesi koyar.
     *
     * @param array<string, mixed> $server
     */
    public static function baslikta(array $server): ?string
    {
        $ham = trim((string) (
            $server['HTTP_AUTHORIZATION']
            ?? $server['REDIRECT_HTTP_AUTHORIZATION']
            ?? ''
        ));

        if (!preg_match('/\Abearer\s+(\S+)\z/i', $ham, $m)) {
            return null;
        }

        return $m[1];
    }
}
