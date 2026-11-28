<?php

namespace App\Expense\Domain\Exception;

use RuntimeException;

final class ExpenseNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $expenseId,
        ?\Throwable $previous = null
    ) {
        parent::__construct('Expense was not found in this family.', 0, $previous);
    }
}
