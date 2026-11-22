<?php

namespace App\Expense\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Domain\ValueObject\ExpenseId;

final readonly class DeleteExpenseCommand
{
    public function __construct(
        public ExpenseId $expenseId,
        public FamilyId $familyId,
        public UserId $userId,
    ) {
    }
}
