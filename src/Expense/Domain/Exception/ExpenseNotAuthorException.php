<?php

namespace App\Expense\Domain\Exception;

use RuntimeException;

final class ExpenseNotAuthorException extends RuntimeException
{
    public function __construct(
        public readonly string $expenseId,
        public readonly string $userId,
        ?\Throwable $previous = null
    ) {
        parent::__construct('Only the author can edit this expense.', 0, $previous);
    }
}
