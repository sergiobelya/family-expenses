<?php

namespace App\Tests\Unit\Domain\Core\ValueObject;

use App\Core\Domain\ValueObject\CurrencyCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CurrencyCodeTest extends TestCase
{
    public function testValidCurrencyCode(): void
    {
        $pln = new CurrencyCode('pln');
        $this->assertSame('PLN', (string) $pln);
        $this->assertSame('PLN', $pln->value);

        $pln2 = CurrencyCode::fromString('PLN');
        $this->assertTrue($pln->equals($pln2));

        $eur = new CurrencyCode('EUR');
        $this->assertFalse($pln->equals($eur));
    }

    public function testInvalidCurrencyCodeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CurrencyCode('INVALID');
    }

    public function testEmptyCurrencyCodeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CurrencyCode('');
    }
}
