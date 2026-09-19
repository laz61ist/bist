<?php

declare(strict_types=1);

namespace Aidat\Tests\Unit;

use Aidat\Core\Validator;
use Aidat\Tests\TestCase;

final class ValidatorTest extends TestCase
{
    public function testZorunluVeTurkceMesaj(): void
    {
        $v = Validator::make(['email' => 'x'], ['name' => 'required', 'email' => 'required|email'], ['name' => 'ad soyad', 'email' => 'e-posta']);
        self::assertTrue($v->fails());
        self::assertSame('Ad soyad alanı zorunludur.', $v->errors()['name']);
        self::assertSame('E-posta geçerli bir e-posta adresi olmalıdır.', $v->errors()['email']);
    }

    public function testParaVeTarihDonusumu(): void
    {
        $v = Validator::make(['amount' => '1.250,50', 'date' => '19.09.2026', 'period' => '2026-09'], ['amount' => 'required|money_positive', 'date' => 'required|date', 'period' => 'required|period']);
        self::assertTrue($v->passes(), json_encode($v->errors()));
        self::assertSame(125050, $v->validated()['amount']);
        self::assertSame('2026-09-19', $v->validated()['date']);
    }

    public function testNullableBosAlanNullDoner(): void
    {
        $v = Validator::make(['notes' => ''], ['notes' => 'nullable|max:10']);
        self::assertTrue($v->passes());
        self::assertNull($v->validated()['notes']);
    }

    public function testUniqueVeExists(): void
    {
        $v = Validator::make(['email' => 'test@aidat.local', 'unit' => 999], ['email' => 'required|email|unique:users,email', 'unit' => 'required|exists:units,id'], [], $this->db);
        self::assertTrue($v->fails());
        self::assertArrayHasKey('email', $v->errors());
        self::assertArrayHasKey('unit', $v->errors());
    }

    public function testInKeysListeden(): void
    {
        $ok = Validator::make(['t' => 'daire'], ['t' => 'required|in_keys:lists.unit_types']);
        $bad = Validator::make(['t' => 'villa'], ['t' => 'required|in_keys:lists.unit_types']);
        self::assertTrue($ok->passes());
        self::assertTrue($bad->fails());
    }
}
