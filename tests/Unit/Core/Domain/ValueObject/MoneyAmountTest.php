<?php

namespace App\Tests\Unit\Core\Domain\ValueObject;

use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\MoneyAmount;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class MoneyAmountTest extends TestCase
{
    public function testFromDecimalCreatesValidAmount(): void
    {
        $amount = MoneyAmount::fromDecimal('100.50', CurrencyCode::fromString('PLN'));

        $this->assertSame('10050', $amount->getMinorUnits());
        $this->assertTrue($amount->getCurrency()->equals(CurrencyCode::fromString('PLN')));
    }

    public function testFromIntCreatesAmountFromInteger(): void
    {
        $amount = MoneyAmount::fromInt(10050, CurrencyCode::fromString('EUR'));

        $this->assertSame('10050', $amount->getMinorUnits());
        $this->assertTrue($amount->getCurrency()->equals(CurrencyCode::fromString('EUR')));
    }

    public function testAddSameCurrency(): void
    {
        $a = MoneyAmount::fromDecimal('100.00', CurrencyCode::fromString('PLN'));
        $b = MoneyAmount::fromDecimal('50.50', CurrencyCode::fromString('PLN'));

        $sum = $a->add($b);

        $this->assertSame('15050', $sum->getMinorUnits());
        $this->assertTrue($sum->getCurrency()->equals(CurrencyCode::fromString('PLN')));
    }

    public function testSubtractSameCurrency(): void
    {
        $a = MoneyAmount::fromDecimal('200.00', CurrencyCode::fromString('USD'));
        $b = MoneyAmount::fromDecimal('50.00', CurrencyCode::fromString('USD'));

        $diff = $a->subtract($b);

        $this->assertSame('15000', $diff->getMinorUnits());
        $this->assertTrue($diff->getCurrency()->equals(CurrencyCode::fromString('USD')));
    }

    public function testConstructorRejectsZero(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        new MoneyAmount(new Money('0', CurrencyCode::fromString('PLN')->toMoneyCurrency()));
    }

    public function testConstructorRejectsNegative(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        new MoneyAmount(new Money('-1000', CurrencyCode::fromString('PLN')->toMoneyCurrency()));
    }

    public function testFromDecimalRejectsZero(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        MoneyAmount::fromDecimal('0', CurrencyCode::fromString('PLN'));
    }

    public function testFromDecimalRejectsNegative(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        MoneyAmount::fromDecimal('-10.00', CurrencyCode::fromString('PLN'));
    }

    public function testFromIntRejectsZero(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        MoneyAmount::fromInt(0, CurrencyCode::fromString('PLN'));
    }

    public function testFromIntRejectsNegative(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        MoneyAmount::fromInt(-1000, CurrencyCode::fromString('PLN'));
    }

    public function testFromDecimalRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_invalid_format');
        /** @phpstan-ignore-next-line intentionally passing non-numeric string to test validation */
        MoneyAmount::fromDecimal('not_a_number', CurrencyCode::fromString('PLN'));
    }

    public function testFromDecimalWithIskZeroExponent(): void
    {
        // ISK has exponent 0, so "100" should work (no decimal conversion needed)
        $amount = MoneyAmount::fromDecimal('100', CurrencyCode::fromString('ISK'));
        $this->assertSame('100', $amount->getMinorUnits());
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
