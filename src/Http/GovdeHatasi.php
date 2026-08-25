<?php

declare(strict_types=1);

namespace Bist\Http;

use RuntimeException;

/**
 * Istek govdesi kabul edilemez oldugunda atilir.
 *
 * Mesaj DOGRUDAN kullaniciya donebilecek sekilde yazilir: ic sinif adi,
 * dosya yolu veya istisna zinciri icermez (anayasa madde IX ruhu, #15/S4).
 */
final class GovdeHatasi extends RuntimeException
{
    public function __construct(
        public readonly int $durumKodu,
        string $mesaj,
    ) {
        parent::__construct($mesaj, $durumKodu);
    }

    public static function cokBuyuk(int $azamiBayt): self
    {
        return new self(413, sprintf(
            'İstek gövdesi çok büyük. Azami %d bayt kabul edilir.',
            $azamiBayt,
        ));
    }

    public static function bicimsiz(): self
    {
        return new self(422, 'İstek gövdesi geçerli JSON değil.');
    }

    public static function cokDerin(int $azamiDerinlik): self
    {
        return new self(422, sprintf(
            'İstek gövdesi çok derin iç içe. Azami %d seviye kabul edilir.',
            $azamiDerinlik,
        ));
    }

    public static function nesneVeyaDiziBekleniyor(): self
    {
        return new self(422, 'İstek gövdesi bir JSON nesnesi veya dizisi olmalı.');
    }
}
