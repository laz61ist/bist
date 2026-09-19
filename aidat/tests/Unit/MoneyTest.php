<?php

declare(strict_types=1);

namespace Aidat\Tests\Unit;

use Aidat\Core\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /** Türkçe ondalık: nokta binlik, virgül ondalık. */
    public function testTurkceBicimParse(): void
    {
        self::assertSame(125050, Money::parse('1.250,50'));
        self::assertSame(125050, Money::parse('1250,5'));
        self::assertSame(1063680, Money::parse('10.636,8'));
        self::assertSame(125000, Money::parse('1.250'));
        self::assertSame(125000000, Money::parse('1.250.000'));
    }

    public function testIngilizceBicimParse(): void
    {
        self::assertSame(125050, Money::parse('1250.50'));
        self::assertSame(125075, Money::parse('1,250.75'));
        self::assertSame(1250, Money::parse('12.5'));
        self::assertSame(125000000, Money::parse('1,250,000'));
    }

    public function testSembolVeNegatif(): void
    {
        self::assertSame(250000, Money::parse('2.500,00 ₺'));
        self::assertSame(-1500, Money::parse('-15'));
        self::assertSame(0, Money::parse(''));
        self::assertSame(0, Money::parse(null));
    }

    public function testGecersizGirdiHataVerir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::parse('abc');
    }

    public function testFormat(): void
    {
        self::assertSame('1.250,50 ₺', Money::format(125050));
        self::assertSame('-3.400,85 ₺', Money::format(-340085));
        self::assertSame('0,00', Money::format(0, false));
        self::assertSame('1250.50', Money::decimal(125050));
        self::assertSame('1.250,50', Money::input(125050));
    }

    public function testSplitToplamiKorur(): void
    {
        $split = Money::split(100000, ['a' => 1, 'b' => 1, 'c' => 1]);
        self::assertSame(100000, array_sum($split));
        self::assertSame(33333, $split['a']);
        self::assertSame(33334, $split['c']);
        $w = Money::split(250000, [1 => 95, 2 => 120, 3 => 140]);
        self::assertSame(250000, array_sum($w));
    }

    public function testYaziylaTutar(): void
    {
        self::assertSame('iki bin beş yüz Türk Lirası', Money::words(250000));
        self::assertSame('bin iki yüz elli Türk Lirası elli Kuruş', Money::words(125050));
        self::assertSame('yüz Türk Lirası', Money::words(10000));
        self::assertSame('sıfır Türk Lirası', Money::words(0));
    }
}
