<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Expense;

use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Flow\Expense\ExpenseCardRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ExpenseCardRenderer::class)]
class ExpenseCardRendererTest extends TestCase
{
    private ExpenseCardRenderer $renderer;

    protected function setUp(): void
    {
        $telegramClient = $this->createMock(TelegramApiClientInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $this->renderer = new ExpenseCardRenderer($telegramClient, $translator);
    }

    // --- formatAmount: standard precision 2 ---

    public function testFormatAmountWithTwoDecimals(): void
    {
        $result = $this->renderer->formatAmount('125.50', 'USD');

        $this->assertSame('125.50 USD', $result);
    }

    public function testFormatAmountIntegerValueWithTwoDecimals(): void
    {
        $result = $this->renderer->formatAmount('500', 'PLN');

        $this->assertSame('500.00 PLN', $result);
    }

    public function testFormatAmountTrimsExcessDecimals(): void
    {
        // Input has more decimals than currency precision; bcadd truncates
        $result = $this->renderer->formatAmount('10.123', 'EUR');

        $this->assertSame('10.12 EUR', $result);
    }

    public function testFormatAmountWithTrailingZeros(): void
    {
        $result = $this->renderer->formatAmount('100.10', 'GBP');

        $this->assertSame('100.10 GBP', $result);
    }

    public function testFormatAmountZero(): void
    {
        $result = $this->renderer->formatAmount('0', 'USD');

        $this->assertSame('0.00 USD', $result);
    }

    // --- formatAmount: zero precision currencies ---

    public function testFormatAmountZeroPrecisionCurrency(): void
    {
        $result = $this->renderer->formatAmount('500', 'ISK');

        $this->assertSame('500 ISK', $result);
    }

    public function testFormatAmountZeroPrecisionDropsFractionalPart(): void
    {
        $result = $this->renderer->formatAmount('500.99', 'ISK');

        $this->assertSame('500 ISK', $result);
    }

    // --- formatAmount: unknown currency fallback to precision 2 ---

    public function testFormatAmountUnknownCurrencyDefaultsToTwoDecimals(): void
    {
        $result = $this->renderer->formatAmount('99.5', 'XXX');

        $this->assertSame('99.50 XXX', $result);
    }

    // --- formatAmount: large amounts ---

    public function testFormatAmountLargeValueDropsFractionalPart(): void
    {
        $result = $this->renderer->formatAmount('99999999.5', 'ISK');

        $this->assertSame('99999999 ISK', $result);
    }

    public function testFormatAmountLargeValue(): void
    {
        $result = $this->renderer->formatAmount('99999999.99', 'USD');

        $this->assertSame('99999999.99 USD', $result);
    }

    public function testFormatAmountMaxValue(): void
    {
        $result = $this->renderer->formatAmount('100000000', 'UAH');

        $this->assertSame('100000000.00 UAH', $result);
    }

    public function testFormatAmountVeryLargeValue(): void
    {
        $result = $this->renderer->formatAmount('999999999999999.99', 'UAH');

        $this->assertSame('999999999999999.99 UAH', $result);
    }

    public function testFormatAmountVeryLargeInteger(): void
    {
        $result = $this->renderer->formatAmount('9999999999999999', 'ISK');

        $this->assertSame('9999999999999999 ISK', $result);
    }
}
