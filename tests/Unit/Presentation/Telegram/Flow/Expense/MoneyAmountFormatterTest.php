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

    // --- format: standard precision 2 ---

    public function testFormatWithTwoDecimals(): void
    {
        $result = $this->formatter->format('125.50', 'USD');

        $this->assertSame('125.50 USD', $result);
    }

    public function testFormatIntegerValueWithTwoDecimals(): void
    {
        $result = $this->formatter->format('500', 'PLN');

        $this->assertSame('500.00 PLN', $result);
    }

    public function testFormatTrimsExcessDecimals(): void
    {
        // Input has more decimals than currency precision; bcadd truncates
        $result = $this->formatter->format('10.123', 'EUR');

        $this->assertSame('10.12 EUR', $result);
    }

    public function testFormatWithTrailingZeros(): void
    {
        $result = $this->formatter->format('100.10', 'GBP');

        $this->assertSame('100.10 GBP', $result);
    }

    public function testFormatZero(): void
    {
        $result = $this->formatter->format('0', 'USD');

        $this->assertSame('0.00 USD', $result);
    }

    // --- format: zero precision currencies ---

    public function testFormatZeroPrecisionCurrency(): void
    {
        $result = $this->formatter->format('500', 'ISK');

        $this->assertSame('500 ISK', $result);
    }

    public function testFormatZeroPrecisionDropsFractionalPart(): void
    {
        $result = $this->formatter->format('500.99', 'ISK');

        $this->assertSame('500 ISK', $result);
    }

    // --- format: unknown currency fallback to precision 2 ---

    public function testFormatUnknownCurrencyDefaultsToTwoDecimals(): void
    {
        $result = $this->formatter->format('99.5', 'XXX');

        $this->assertSame('99.50 XXX', $result);
    }

    // --- format: large amounts ---

    public function testFormatLargeValueDropsFractionalPart(): void
    {
        $result = $this->formatter->format('99999999.5', 'ISK');

        $this->assertSame('99999999 ISK', $result);
    }

    public function testFormatLargeValue(): void
    {
        $result = $this->formatter->format('99999999.99', 'USD');

        $this->assertSame('99999999.99 USD', $result);
    }

    public function testFormatMaxValue(): void
    {
        $result = $this->formatter->format('100000000', 'UAH');

        $this->assertSame('100000000.00 UAH', $result);
    }

    public function testFormatVeryLargeValue(): void
    {
        $result = $this->formatter->format('999999999999999.99', 'UAH');

        $this->assertSame('999999999999999.99 UAH', $result);
    }

    public function testFormatVeryLargeInteger(): void
    {
        $result = $this->formatter->format('9999999999999999', 'ISK');

        $this->assertSame('9999999999999999 ISK', $result);
    }
}
