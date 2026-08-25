<?php

declare(strict_types=1);

namespace Bist\Data;

use Bist\Domain\EksiklikNedeni;
use Bist\Domain\Metrik;

/**
 * Hicbir veri kaynagi bagli degilken kullanilan kaynak.
 *
 * Her metrigi bos dondurur ve nedenini yazar. Bu bir yer tutucu degil,
 * kasitli davranistir: G-15 geregi temsili veri uretilmez. Kokpit calisir,
 * tum metrikler "—" gorunur, kullanici neyin eksik oldugunu gorur.
 */
final class BosKaynak implements VeriKaynagi
{
    public function __construct(
        private readonly string $neden = 'Veri kaynağı bağlanmadı.',
    ) {
    }

    public function ad(): string
    {
        return 'bağlı kaynak yok';
    }

    public function bagli(): bool
    {
        return false;
    }

    public function metrik(string $sirketKodu, string $anahtar): Metrik
    {
        $t = MetrikSozlugu::tanim($anahtar);

        return new Metrik(
            ad: $t['ad'],
            neDemek: $t['neDemek'],
            eksikNedeni: $this->neden,
            nedenTipi: EksiklikNedeni::KAYNAK_YOK,
        );
    }
}
