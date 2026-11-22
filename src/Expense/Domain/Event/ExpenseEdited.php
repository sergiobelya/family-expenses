<?php

namespace App\Expense\Domain\Event;

use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class ExpenseEdited extends BaseExpenseEvent
{
    public function __construct(
        ExpenseId $expenseId,
        FamilyId $familyId,
        UserId $actorUserId,
        array $beforeData,
        array $afterData,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct(
            action: 'expense.edited',
            expenseId: $expenseId,
            familyId: $familyId,
            actorUserId: $actorUserId,
            beforeData: $beforeData,
            afterData: $afterData,
            occurredAt: $occurredAt
        );
    }
}
