<?php

declare(strict_types=1);

namespace Bist\Config;

/**
 * Calisma ortami.
 *
 * Varsayilan PRODUCTION'dir ve bu kasitlidir: yapilandirma eksik veya
 * yanlis yazilmissa sistem EN KISITLI moda duser, en gevsegine degil.
 * Yazim hatasi guvenligi gevsetmemeli.
 */
enum Ortam: string
{
    case PRODUCTION = 'production';
    case DEVELOPMENT = 'development';
    case TEST = 'test';

    /** Hata ayrintisi kullaniciya gosterilebilir mi (D4 / #8). */
    public function hataDetayiGoster(): bool
    {
        return $this !== self::PRODUCTION;
    }
}
