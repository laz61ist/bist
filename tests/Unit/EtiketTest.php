<?php

declare(strict_types=1);

namespace Bist\Tests\Unit;

use Bist\Domain\Etiket;
use PHPUnit\Framework\TestCase;

final class EtiketTest extends TestCase
{
    public function testProjeTalimatindakiBesEtiketVardir(): void
    {
        $adlar = array_map(static fn (Etiket $e): string => $e->name, Etiket::cases());

        self::assertSame(
            ['KAYNAKSIZ', 'DUZELTME_YOK', 'DUSUK_LIKIDITE', 'AYKIRI', 'TAM'],
            $adlar,
        );
    }

    public function testEtiketinGorunenMetniTurkcedir(): void
    {
        self::assertSame('KAYNAKSIZ', Etiket::KAYNAKSIZ->metin());
        self::assertSame('DÜZELTME YOK', Etiket::DUZELTME_YOK->metin());
        self::assertSame('DÜŞÜK LİKİDİTE', Etiket::DUSUK_LIKIDITE->metin());
        self::assertSame('AYKIRI', Etiket::AYKIRI->metin());
        self::assertSame('TAM', Etiket::TAM->metin());
    }

    public function testEtiketRengiTasarimSistemineUyar(): void
    {
        // proje-talimati.md: KAYNAKSIZ ve AYKIRI turuncu, TAM yesilimsi, digerleri gri
        self::assertSame('turuncu', Etiket::KAYNAKSIZ->renk());
        self::assertSame('turuncu', Etiket::AYKIRI->renk());
        self::assertSame('yesil', Etiket::TAM->renk());
        self::assertSame('gri', Etiket::DUZELTME_YOK->renk());
        self::assertSame('gri', Etiket::DUSUK_LIKIDITE->renk());
    }
}
