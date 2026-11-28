<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Expense;

use App\Presentation\Telegram\Flow\Expense\MoneyAmountFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoneyAmountFormatter::class)]
class MoneyAmountFormatterTest extends TestCase
{
    private MoneyAmountFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MoneyAmountFormatter();
    }

    public function testFormatWithTwoDecimals(): void
    {
        self::assertSame('125.50 USD', $this->formatter->format('125.50', 'USD'));
    }

    public function testFormatIntegerValueWithTwoDecimals(): void
    {
        self::assertSame('500.00 PLN', $this->formatter->format('500', 'PLN'));
    }

    public function testFormatTrimsExcessDecimals(): void
    {
        self::assertSame('10.12 EUR', $this->formatter->format('10.123', 'EUR'));
    }

    public function testFormatWithTrailingZeros(): void
    {
        self::assertSame('100.10 GBP', $this->formatter->format('100.10', 'GBP'));
    }

    public function testFormatZero(): void
    {
        self::assertSame('0.00 USD', $this->formatter->format('0', 'USD'));
    }

    public function testFormatZeroPrecisionCurrency(): void
    {
        self::assertSame('500 ISK', $this->formatter->format('500', 'ISK'));
    }

    public function testFormatZeroPrecisionDropsFractionalPart(): void
    {
        self::assertSame('500 ISK', $this->formatter->format('500.99', 'ISK'));
    }

    public function testFormatUnknownCurrencyDefaultsToTwoDecimals(): void
    {
        self::assertSame('99.50 XXX', $this->formatter->format('99.5', 'XXX'));
    }

    public function testFormatLargeValueDropsFractionalPart(): void
    {
        self::assertSame('99999999 ISK', $this->formatter->format('99999999.5', 'ISK'));
    }

    public function testFormatLargeValue(): void
    {
        self::assertSame('99999999.99 USD', $this->formatter->format('99999999.99', 'USD'));
    }

    public function testFormatMaxValue(): void
    {
        self::assertSame('100000000.00 UAH', $this->formatter->format('100000000', 'UAH'));
    }

    public function testFormatVeryLargeValue(): void
    {
        self::assertSame('999999999999999.99 UAH', $this->formatter->format('999999999999999.99', 'UAH'));
    }

    public function testFormatVeryLargeInteger(): void
    {
        self::assertSame('9999999999999999 ISK', $this->formatter->format('9999999999999999', 'ISK'));
    }

    public function testFormatUsesLocaleDecimalSeparator(): void
    {
        self::assertSame('125,50 EUR', $this->formatter->format('125.5', 'EUR', 'uk'));
        self::assertSame('12.345', $this->formatter->formatNumber('12.345', 'ISK', 'en'));
    }
}