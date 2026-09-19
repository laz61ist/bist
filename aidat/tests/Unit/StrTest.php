<?php

declare(strict_types=1);

namespace Aidat\Tests\Unit;

use Aidat\Core\Str;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    public function testTurkceBuyukKucuk(): void
    {
        self::assertSame('İSTANBUL', Str::upperTr('istanbul'));
        self::assertSame('ılık', Str::lowerTr('ILIK'));
        self::assertSame('AD', Str::initials('Ayşe Demir'));
        self::assertSame('cinar-sitesi', Str::slug('Çınar Sitesi'));
    }

    public function testIbanVeTckn(): void
    {
        self::assertTrue(Str::validIban('TR33 0006 1005 1978 6457 8413 26'));
        self::assertFalse(Str::validIban('TR33 0006 1005 1978 6457 8413 27'));
        self::assertTrue(Str::validTckn('10000000146'));
        self::assertFalse(Str::validTckn('12345678901'));
    }

    public function testMaskeleme(): void
    {
        self::assertSame('10*******46', Str::maskIdentity('10000000146'));
        self::assertSame('0532 123 45 67', Str::formatPhone('05321234567'));
        self::assertSame('TR33 0006 1005 1978 6457 8413 26', Str::formatIban('TR330006100519786457841326'));
    }
}
