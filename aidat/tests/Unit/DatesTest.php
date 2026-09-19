<?php

declare(strict_types=1);

namespace Aidat\Tests\Unit;

use Aidat\Core\Dates;
use PHPUnit\Framework\TestCase;

final class DatesTest extends TestCase
{
    public function testParse(): void
    {
        self::assertSame('2026-09-19', Dates::parse('19.09.2026'));
        self::assertSame('2026-09-19', Dates::parse('2026-09-19'));
        self::assertSame('2026-09-19', Dates::parse('19/09/2026'));
        self::assertNull(Dates::parse('31.02.2026'));
        self::assertNull(Dates::parse('bugün'));
    }

    public function testBicimler(): void
    {
        self::assertSame('19.09.2026', Dates::tr('2026-09-19'));
        self::assertSame('19 Eylül 2026', Dates::trLong('2026-09-19'));
        self::assertSame('Eylül 2026', Dates::period('2026-09'));
        self::assertSame('—', Dates::tr(null));
    }

    public function testAyHesaplari(): void
    {
        self::assertSame('2026-12', Dates::addMonths('2026-09', 3));
        self::assertSame('2025-11', Dates::addMonths('2026-01', -2));
        self::assertSame(['2026-11', '2026-12', '2027-01'], Dates::periodRange('2026-11', '2027-01'));
        self::assertSame('2026-02-28', Dates::dayOfPeriod('2026-02', 31));
        self::assertSame(0, Dates::monthsBetween('2026-01-10', '2026-01-10'));
        self::assertSame(1, Dates::monthsBetween('2026-01-10', '2026-01-11'));
        self::assertSame(1, Dates::monthsBetween('2026-01-10', '2026-02-10'));
        self::assertSame(2, Dates::monthsBetween('2026-01-10', '2026-02-11'));
        self::assertSame(45, Dates::daysBetween('2026-01-01', '2026-02-15'));
    }
}
