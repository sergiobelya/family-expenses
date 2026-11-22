<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\ExpenseId;

final class ExpenseIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return ExpenseId::class;
    }

    public function getName(): string
    {
        return 'expense_id';
    }
}
