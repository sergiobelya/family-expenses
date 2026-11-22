<?php

namespace App\Tests\Unit\Core\Domain\ValueObject;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\MoneyAmount;
use PHPUnit\Framework\TestCase;

final class MoneyAmountTest extends TestCase
{
    public function testFromDecimalCreatesValidAmount(): void
    {
        $amount = MoneyAmount::fromDecimal('100.50', CurrencyCode::fromString('PLN'));

        $this->assertSame('10050', $amount->getAmount());
        $this->assertTrue($amount->getCurrency()->equals(CurrencyCode::fromString('PLN')));
    }

    public function testFromIntCreatesAmountFromInteger(): void
    {
        $amount = MoneyAmount::fromInt(10050, CurrencyCode::fromString('EUR'));

        $this->assertSame('10050', $amount->getAmount());
        $this->assertTrue($amount->getCurrency()->equals(CurrencyCode::fromString('EUR')));
    }

    public function testAddSameCurrency(): void
    {
        $a = MoneyAmount::fromDecimal('100.00', CurrencyCode::fromString('PLN'));
        $b = MoneyAmount::fromDecimal('50.50', CurrencyCode::fromString('PLN'));

        $sum = $a->add($b);

        $this->assertSame('15050', $sum->getAmount());
        $this->assertTrue($sum->getCurrency()->equals(CurrencyCode::fromString('PLN')));
    }

    public function testSubtractSameCurrency(): void
    {
        $a = MoneyAmount::fromDecimal('200.00', CurrencyCode::fromString('USD'));
        $b = MoneyAmount::fromDecimal('50.00', CurrencyCode::fromString('USD'));

        $diff = $a->subtract($b);

        $this->assertSame('15000', $diff->getAmount());
        $this->assertTrue($diff->getCurrency()->equals(CurrencyCode::fromString('USD')));
    }

    public function testIsZero(): void
    {
        $zero = MoneyAmount::fromDecimal('0', CurrencyCode::fromString('PLN'));
        $nonZero = MoneyAmount::fromDecimal('1.00', CurrencyCode::fromString('PLN'));

        $this->assertTrue($zero->isZero());
        $this->assertFalse($nonZero->isZero());
    }

    public function testIsPositive(): void
    {
        $positive = MoneyAmount::fromDecimal('10.00', CurrencyCode::fromString('PLN'));
        $zero = MoneyAmount::fromDecimal('0', CurrencyCode::fromString('PLN'));
        $negative = MoneyAmount::fromDecimal('-10.00', CurrencyCode::fromString('PLN'));

        $this->assertTrue($positive->isPositive());
        $this->assertFalse($zero->isPositive());
        $this->assertFalse($negative->isPositive());
    }

    public function testIsNegative(): void
    {
        $negative = MoneyAmount::fromDecimal('-10.00', CurrencyCode::fromString('PLN'));
        $zero = MoneyAmount::fromDecimal('0', CurrencyCode::fromString('PLN'));
        $positive = MoneyAmount::fromDecimal('10.00', CurrencyCode::fromString('PLN'));

        $this->assertTrue($negative->isNegative());
        $this->assertFalse($zero->isNegative());
        $this->assertFalse($positive->isNegative());
    }

    public function testGreaterThan(): void
    {
        $a = MoneyAmount::fromDecimal('200.00', CurrencyCode::fromString('PLN'));
        $b = MoneyAmount::fromDecimal('100.00', CurrencyCode::fromString('PLN'));

        $this->assertTrue($a->greaterThan($b));
        $this->assertFalse($b->greaterThan($a));
        $this->assertFalse($a->greaterThan($a));
    }

    public function testLessThan(): void
    {
        $a = MoneyAmount::fromDecimal('100.00', CurrencyCode::fromString('PLN'));
        $b = MoneyAmount::fromDecimal('200.00', CurrencyCode::fromString('PLN'));

        $this->assertTrue($a->lessThan($b));
        $this->assertFalse($b->lessThan($a));
        $this->assertFalse($a->lessThan($a));
    }

    public function testEquals(): void
    {
        $a = MoneyAmount::fromDecimal('100.50', CurrencyCode::fromString('PLN'));
        $b = MoneyAmount::fromDecimal('100.50', CurrencyCode::fromString('PLN'));
        $c = MoneyAmount::fromDecimal('200.00', CurrencyCode::fromString('PLN'));

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testGetMoneyReturnsMoneyInstance(): void
    {
        $amount = MoneyAmount::fromDecimal('100.50', CurrencyCode::fromString('PLN'));

        $money = $amount->getMoney();
        $this->assertInstanceOf(\Money\Money::class, $money);
        $this->assertSame('10050', $money->getAmount());
    }
}
