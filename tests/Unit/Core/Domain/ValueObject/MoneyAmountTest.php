<?php

namespace App\Tests\Unit\Core\Domain\ValueObject;

use App\Core\Domain\Exception\ErrorType;
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

    public function testGetMaxPrecisionReturnsCorrectValue(): void
    {
        $this->assertSame(2, MoneyAmount::getMaxPrecision('PLN'));
        $this->assertSame(2, MoneyAmount::getMaxPrecision('EUR'));
        $this->assertSame(0, MoneyAmount::getMaxPrecision('ISK'));
        $this->assertSame(2, MoneyAmount::getMaxPrecision('UNKNOWN')); // default fallback
    }

    public function testFromDecimalWithValidationCreatesValidAmount(): void
    {
        $amount = MoneyAmount::fromDecimalWithValidation('125.50', CurrencyCode::fromString('PLN'));

        $this->assertSame('12550', $amount->getMinorUnits());
    }

    public function testFromDecimalWithValidationRejectsTooPrecise(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_too_precise');
        MoneyAmount::fromDecimalWithValidation('12.345', CurrencyCode::fromString('PLN'));
    }

    public function testFromDecimalWithValidationRejectsTooPreciseForZeroDecimalCurrency(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_too_precise_zero');
        MoneyAmount::fromDecimalWithValidation('500.5', CurrencyCode::fromString('ISK'));
    }

    public function testFromDecimalWithValidationAcceptsZeroDecimalCurrency(): void
    {
        $amount = MoneyAmount::fromDecimalWithValidation('500', CurrencyCode::fromString('ISK'));
        $this->assertSame('500', $amount->getMinorUnits());
    }

    public function testFromDecimalWithValidationRejectsTooLarge(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_too_large');
        MoneyAmount::fromDecimalWithValidation('100000001', CurrencyCode::fromString('PLN'));
    }

    public function testFromDecimalWithValidationAcceptsMaxAmount(): void
    {
        $amount = MoneyAmount::fromDecimalWithValidation('100000000', CurrencyCode::fromString('PLN'));
        $this->assertSame('10000000000', $amount->getMinorUnits());
    }

    public function testFromDecimalWithValidationRejectsZero(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        MoneyAmount::fromDecimalWithValidation('0', CurrencyCode::fromString('PLN'));
    }

    public function testFromDecimalWithValidationRejectsNegative(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        MoneyAmount::fromDecimalWithValidation('-10.00', CurrencyCode::fromString('PLN'));
    }

    public function testFromDecimalWithValidationWithCustomMaxAmount(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_too_large');
        MoneyAmount::fromDecimalWithValidation('500', CurrencyCode::fromString('PLN'), 100);
    }
}
