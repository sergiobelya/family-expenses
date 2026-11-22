<?php

namespace App\Expense\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Infrastructure\Persistence\Doctrine\Type\UuidValueObjectType;
use App\Expense\Domain\ValueObject\ExpenseSaveAttemptId;

final class ExpenseSaveAttemptIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return ExpenseSaveAttemptId::class;
    }

    public function getName(): string
    {
        return 'expense_save_attempt_id';
    }
}
