<?php

namespace App\Tests\Unit\Application\Expense\Validation;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\MoneyAmount;
use App\Expense\Application\Validation\ExpenseAmountValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpenseAmountValidator::class)]
final class ExpenseAmountValidatorTest extends TestCase
{
    public function testAcceptsAmountAtConfiguredMaximum(): void
    {
        $amount = MoneyAmount::fromDecimalWithValidation('1000.00', CurrencyCode::fromString('PLN'));

        (new ExpenseAmountValidator(1_000))->validate($amount);
        self::assertSame('100000', $amount->getMinorUnits());
    }

    public function testRejectsFractionalAmountAboveConfiguredMaximum(): void
    {
        try {
            $amount = MoneyAmount::fromDecimalWithValidation('1000.01', CurrencyCode::fromString('PLN'));
            (new ExpenseAmountValidator(1_000))->validate($amount);
            self::fail('Expected InvalidValueException');
        } catch (InvalidValueException $exception) {
            self::assertSame(ErrorType::MONEY_AMOUNT_TOO_LARGE, $exception->errorType);
            self::assertSame(['maxAmount' => 1_000], $exception->properties);
        }
    }
}
