<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\MoneyAmount;
use App\Presentation\Telegram\Flow\Expense\AmountParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AmountParser::class)]
class AmountParserTest extends TestCase
{
    private AmountParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AmountParser();
    }

    // --- Valid amounts ---

    public function testParseValidAmountWithTwoDecimals(): void
    {
        $amount = $this->parser->parse('125.50', 'PLN');

        $this->assertInstanceOf(MoneyAmount::class, $amount);
        $this->assertSame('12550', $amount->getMinorUnits());
    }

    public function testParseValidAmountWithCommaSeparator(): void
    {
        $amount = $this->parser->parse('125,50', 'PLN');

        $this->assertInstanceOf(MoneyAmount::class, $amount);
        $this->assertSame('12550', $amount->getMinorUnits());
    }

    public function testParseValidIntegerAmount(): void
    {
        $amount = $this->parser->parse('500', 'PLN');

        $this->assertInstanceOf(MoneyAmount::class, $amount);
        $this->assertSame('50000', $amount->getMinorUnits());
    }

    public function testParseValidAmountWithLeadingZeros(): void
    {
        $amount = $this->parser->parse('00125.50', 'PLN');

        $this->assertInstanceOf(MoneyAmount::class, $amount);
        $this->assertSame('12550', $amount->getMinorUnits());
    }

    public function testParseValidZeroDecimalCurrency(): void
    {
        $amount = $this->parser->parse('500', 'ISK');

        $this->assertInstanceOf(MoneyAmount::class, $amount);
        $this->assertSame('500', $amount->getMinorUnits());
    }

    // --- Invalid format ---

    public function testParseEmptyString(): void
    {
        try {
            $this->parser->parse('', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    public function testParseWhitespaceOnly(): void
    {
        try {
            $this->parser->parse('   ', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    public function testParseNonNumericInput(): void
    {
        try {
            $this->parser->parse('abc', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    public function testParseMultipleDecimalSeparators(): void
    {
        try {
            $this->parser->parse('10.00.00', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    public function testParseNegativeAmount(): void
    {
        try {
            $this->parser->parse('-10.50', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    public function testParseAmountWithSign(): void
    {
        try {
            $this->parser->parse('+10.50', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    public function testParseAmountWithCurrencyCode(): void
    {
        try {
            $this->parser->parse('125.50 PLN', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    public function testParseAmountWithThousandsSeparator(): void
    {
        try {
            $this->parser->parse('1,250.50', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    public function testParseDotOnly(): void
    {
        try {
            $this->parser->parse('.', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    public function testParseTrailingDot(): void
    {
        try {
            $this->parser->parse('125.', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_INVALID_FORMAT, $e->errorType);
        }
    }

    // --- Domain validation delegated to MoneyAmount ---

    public function testParseZeroAmount(): void
    {
        try {
            $this->parser->parse('0', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_NOT_POSITIVE, $e->errorType);
        }
    }

    public function testParseZeroWithDecimals(): void
    {
        try {
            $this->parser->parse('0.00', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_NOT_POSITIVE, $e->errorType);
        }
    }

    public function testParseTooPreciseAmount(): void
    {
        try {
            $this->parser->parse('12.345', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_TOO_PRECISE, $e->errorType);
        }
    }

    public function testParseTooPreciseForZeroDecimalCurrency(): void
    {
        try {
            $this->parser->parse('500.5', 'ISK');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_TOO_PRECISE_ZERO, $e->errorType);
        }
    }

    public function testParseTooLargeAmount(): void
    {
        try {
            $this->parser->parse('100000001', 'PLN');
            $this->fail('Expected InvalidValueException');
        } catch (InvalidValueException $e) {
            $this->assertSame(ErrorType::MONEY_AMOUNT_TOO_LARGE, $e->errorType);
        }
    }

    public function testParseMaxAllowedAmount(): void
    {
        $amount = $this->parser->parse('100000000', 'PLN');

        $this->assertInstanceOf(MoneyAmount::class, $amount);
        $this->assertSame('10000000000', $amount->getMinorUnits());
    }

    // --- Whitespace trimming ---

    public function testParseTrimsWhitespace(): void
    {
        $amount = $this->parser->parse('  125.50  ', 'PLN');

        $this->assertInstanceOf(MoneyAmount::class, $amount);
        $this->assertSame('12550', $amount->getMinorUnits());
    }
}
