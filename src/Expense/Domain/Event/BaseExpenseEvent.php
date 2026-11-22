<?php

namespace App\Expense\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Domain\ValueObject\ExpenseId;
use DateTimeImmutable;
use DateTimeZone;

abstract readonly class BaseExpenseEvent implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed>|null $beforeData
     * @param array<string, mixed>|null $afterData
     */
    public function __construct(
        private string $action,
        private ExpenseId $expenseId,
        private FamilyId $familyId,
        private UserId $actorUserId,
        private ?array $beforeData,
        private ?array $afterData,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getFamilyId(): ?FamilyId
    {
        return $this->familyId;
    }

    public function getActorUserId(): UserId
    {
        return $this->actorUserId;
    }

    public function getEntityType(): string
    {
        return 'expense';
    }

    public function getEntityId(): string
    {
        return (string) $this->expenseId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    public function getAfterData(): ?array
    {
        return $this->afterData;
    }

    public function getExpenseId(): ExpenseId
    {
        return $this->expenseId;
    }
}
