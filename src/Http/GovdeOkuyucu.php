<?php

declare(strict_types=1);

namespace Bist\Http;

use Closure;
use JsonException;

/**
 * Istek govdesini SINIRLI okur.
 *
 * D9 (#13): Girdi boyutu sinirsizdi. Olculen sömürü: 200.000 kriterlik
 * 12 MB govde -> 201 Created, 5,13 sn CPU, sqlite 12 MB.
 *
 * Kritik ayrinti: `post_max_size = 8M` ayarli olmasina ragmen istek kabul
 * edildi. PHP bu limiti `application/json` govdelerine UYGULAMIYOR; tek
 * fren `memory_limit`. Bu yuzden sinir uygulama katmaninda zorlanmali.
 *
 * Iki kapi vardir ve ikisi de gereklidir:
 *  1. Content-Length kapisi — govdeyi HIC OKUMADAN reddeder (ucuz)
 *  2. Gercek boyut kapisi   — Content-Length yalan soyleyebilir (kesin)
 */
final class GovdeOkuyucu
{
    public const VARSAYILAN_AZAMI_BAYT = 65536;   // 64 KB
    public const VARSAYILAN_AZAMI_DERINLIK = 8;

    public function __construct(
        private readonly int $azamiBayt = self::VARSAYILAN_AZAMI_BAYT,
        private readonly int $azamiDerinlik = self::VARSAYILAN_AZAMI_DERINLIK,
        private readonly ?int $contentLength = null,
        private readonly ?Closure $govdeSaglayici = null,
    ) {
    }

    /**
     * Govdeyi cozer ve dizi olarak dondurur.
     *
     * @return array<mixed>
     * @throws GovdeHatasi
     */
    public function json(): array
    {
        // 1. kapi: bildirilen boyut zaten asiyorsa govdeyi okumaya hic girme
        if ($this->contentLength !== null && $this->contentLength > $this->azamiBayt) {
            throw GovdeHatasi::cokBuyuk($this->azamiBayt);
        }

        $ham = ($this->govdeSaglayici ?? static fn (): string
            => file_get_contents('php://input') ?: '')();

        // 2. kapi: gercek boyut. Content-Length istemciden gelir, guvenilmez.
        if (strlen($ham) > $this->azamiBayt) {
            throw GovdeHatasi::cokBuyuk($this->azamiBayt);
        }

        if (trim($ham) === '') {
            return [];
        }

        try {
            $cozulmus = json_decode($ham, true, $this->azamiDerinlik, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // Derinlik asimi ile bicim hatasi ayri kullanici mesaji hak eder.
            throw $e->getCode() === JSON_ERROR_DEPTH
                ? GovdeHatasi::cokDerin($this->azamiDerinlik)
                : GovdeHatasi::bicimsiz();
        }

        if (!is_array($cozulmus)) {
            throw GovdeHatasi::nesneVeyaDiziBekleniyor();
        }

        return $cozulmus;
    }
}
