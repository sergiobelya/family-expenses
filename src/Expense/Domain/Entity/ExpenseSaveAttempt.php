<?php

namespace App\Expense\Domain\Entity;

use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Domain\ExpenseSaveAttemptStatus;
use App\Expense\Domain\ValueObject\ExpenseSaveAttemptId;
use DateTimeImmutable;

/**
 * Tracks an expense save attempt for idempotency and recovery.
 *
 * Persisted in PostgreSQL before the first save transaction.
 * The idempotency key is created with the draft and reused for every save attempt.
 * An unresolved attempt must be reconciled before the draft can be replaced.
 *
 * @see docs/system_design.md §3
 * @see docs/specs/add_expense.md §8.2
 */
class ExpenseSaveAttempt
{
    public function __construct(
        public ExpenseSaveAttemptId $id,
        public UserId $userId,
        public FamilyId $familyId,
        public string $idempotencyKey,
        public ?ExpenseId $expenseId,
        public ExpenseSaveAttemptStatus $status,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $completedAt,
    ) {
    }

    public static function pending(
        ExpenseSaveAttemptId $id,
        UserId $userId,
        FamilyId $familyId,
        string $idempotencyKey,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            familyId: $familyId,
            idempotencyKey: $idempotencyKey,
            expenseId: null,
            status: ExpenseSaveAttemptStatus::PENDING,
            createdAt: $now,
            completedAt: null,
        );
    }

    public function markCompleted(ExpenseId $expenseId, DateTimeImmutable $completedAt): void
    {
        $this->expenseId = $expenseId;
        $this->status = ExpenseSaveAttemptStatus::COMPLETED;
        $this->completedAt = $completedAt;
    }

    public function markFailed(DateTimeImmutable $failedAt): void
    {
        $this->status = ExpenseSaveAttemptStatus::FAILED;
        $this->completedAt = $failedAt;
    }

    public function isPending(): bool
    {
        return $this->status === ExpenseSaveAttemptStatus::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === ExpenseSaveAttemptStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === ExpenseSaveAttemptStatus::FAILED;
    }

    public function isResolved(): bool
    {
        return $this->status->isResolved();
    }
}
