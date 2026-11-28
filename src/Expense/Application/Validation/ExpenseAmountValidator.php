<?php

namespace App\Expense\Application\Validation;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\MoneyAmount;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ExpenseAmountValidator
{
    public function __construct(
        #[Autowire('%app.expense.amount.max%')]
        private int $maxAmount = 100_000_000,
    ) {
    }

    /**
     * @throws InvalidValueException if the amount exceeds the configured maximum
     */
    public function validate(MoneyAmount $amount): void
    {
        $maximum = MoneyAmount::fromDecimal((string) $this->maxAmount, $amount->getCurrency());

        if ($amount->greaterThan($maximum)) {
            throw new InvalidValueException(
                ErrorType::MONEY_AMOUNT_TOO_LARGE,
                ['maxAmount' => $this->maxAmount]
            );
        }
    }
}
