<?php

namespace App\Presentation\Telegram\ExceptionMapper;

use App\Expense\Domain\Exception\AllocationReconciliationException;
use App\Expense\Domain\Exception\CategoryNotFoundException;
use App\Expense\Domain\Exception\ExpenseNotAuthorException;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Exception\StoreNotFoundException;
use Symfony\Component\Translation\TranslatableMessage;
use Throwable;

/**
 * Maps Expense domain exceptions to TranslatableMessage.
 */
final readonly class ExpenseExceptionMapper implements ExceptionMapperInterface
{
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof ExpenseNotFoundException
            || $exception instanceof StoreNotFoundException
            || $exception instanceof CategoryNotFoundException
            || $exception instanceof ExpenseNotAuthorException
            || $exception instanceof AllocationReconciliationException;
    }

    public function map(Throwable $exception): TranslatableMessage
    {
        return match (true) {
            $exception instanceof ExpenseNotFoundException => new TranslatableMessage('expense.not_found'),
            $exception instanceof StoreNotFoundException => new TranslatableMessage('expense.store_not_found'),
            $exception instanceof CategoryNotFoundException => new TranslatableMessage('expense.category_not_found'),
            $exception instanceof ExpenseNotAuthorException => new TranslatableMessage('expense.not_author'),
            $exception instanceof AllocationReconciliationException => new TranslatableMessage('expense.allocation_mismatch'),
            default => throw new \RuntimeException('Unhandled exception type in ExpenseExceptionMapper.'),
        };
    }
}
