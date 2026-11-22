<?php

namespace App\Expense\Domain\Entity;

use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\MoneyAmount;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Domain\Event\ExpenseCreated;
use App\Expense\Domain\Event\ExpenseDeleted;
use App\Expense\Domain\Event\ExpenseEdited;
use App\Expense\Domain\Exception\AllocationReconciliationException;
use DateTimeImmutable;
use DateTimeZone;

final class Expense extends AggregateRoot
{
    /** @var ExpenseAllocation[] */
    private array $allocations = [];

    private ExpenseId $id;
    private FamilyId $familyId;
    private UserId $authorUserId;
    private ExpenseStore $store;
    private string $amount;
    private string $currency;
    private DateTimeImmutable $purchaseDate;
    private ?DateTimeImmutable $deletedAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        FamilyId $familyId,
        UserId $authorUserId,
        ExpenseStore $store,
        string $amount,
        string $currency,
        DateTimeImmutable $purchaseDate,
        ?DateTimeImmutable $deletedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?ExpenseId $id = null
    ) {
        $this->id = $id ?? ExpenseId::generate();
        $this->familyId = $familyId;
        $this->authorUserId = $authorUserId;
        $this->store = $store;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->purchaseDate = $purchaseDate;
        $this->deletedAt = $deletedAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param ExpenseAllocation[] $allocations
     */
    public static function create(
        FamilyId $familyId,
        UserId $authorUserId,
        ExpenseStore $store,
        MoneyAmount $amount,
        DateTimeImmutable $purchaseDate,
        array $allocations,
        ?ExpenseId $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expense = new self(
            familyId: $familyId,
            authorUserId: $authorUserId,
            store: $store,
            amount: $amount->getAmount(),
            currency: (string) $amount->getCurrency(),
            purchaseDate: $purchaseDate,
            deletedAt: null,
            createdAt: $now,
            updatedAt: $now,
            id: $id
        );

        foreach ($allocations as $allocation) {
            $expense->addAllocation($allocation);
        }
        $expense->validateAllocations();

        $expense->recordEvent(new ExpenseCreated(
            expenseId: $expense->getId(),
            familyId: $familyId,
            actorUserId: $authorUserId,
            afterData: $expense->snapshot(),
            occurredAt: $now
        ));

        return $expense;
    }

    public function getId(): ExpenseId
    {
        return $this->id;
    }

    public function getFamilyId(): FamilyId
    {
        return $this->familyId;
    }

    public function getAuthorUserId(): UserId
    {
        return $this->authorUserId;
    }

    public function getStore(): ExpenseStore
    {
        return $this->store;
    }

    public function getAmount(): MoneyAmount
    {
        return MoneyAmount::fromInt((int) $this->amount, CurrencyCode::fromString($this->currency));
    }

    public function getPurchaseDate(): DateTimeImmutable
    {
        return $this->purchaseDate;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return ExpenseAllocation[]
     */
    public function getAllocations(): array
    {
        return $this->allocations;
    }

    public function addAllocation(ExpenseAllocation $allocation): void
    {
        $this->allocations[] = $allocation;
    }

    /**
     * @param ExpenseAllocation[] $allocations
     */
    public function setAllocations(array $allocations): void
    {
        $this->allocations = $allocations;
    }

    /**
     * @param ExpenseAllocation[] $allocations
     */
    public function edit(
        ExpenseStore $store,
        MoneyAmount $amount,
        DateTimeImmutable $purchaseDate,
        array $allocations,
        UserId $actorUserId,
        ?DateTimeImmutable $now = null
    ): void {
        $beforeData = $this->snapshot();

        $this->store = $store;
        $this->amount = $amount->getAmount();
        $this->currency = (string) $amount->getCurrency();
        $this->purchaseDate = $purchaseDate;
        $this->allocations = [];

        foreach ($allocations as $allocation) {
            $this->addAllocation($allocation);
        }
        $this->validateAllocations();

        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->recordEvent(new ExpenseEdited(
            expenseId: $this->getId(),
            familyId: $this->familyId,
            actorUserId: $actorUserId,
            beforeData: $beforeData,
            afterData: $this->snapshot(),
            occurredAt: $this->updatedAt
        ));
    }

    public function softDelete(UserId $actorUserId, ?DateTimeImmutable $now = null): void
    {
        if ($this->deletedAt !== null) {
            return;
        }

        $beforeData = $this->snapshot();
        $this->deletedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->updatedAt = $this->deletedAt;

        $this->recordEvent(new ExpenseDeleted(
            expenseId: $this->getId(),
            familyId: $this->familyId,
            actorUserId: $actorUserId,
            beforeData: $beforeData,
            occurredAt: $this->deletedAt
        ));
    }

    private function validateAllocations(): void
    {
        $totalAllocated = '0';
        foreach ($this->allocations as $allocation) {
            /** @var numeric-string $allocationAmount */
            $allocationAmount = $allocation->getAmount()->getAmount();
            $totalAllocated = bcadd($totalAllocated, $allocationAmount, 10);
        }

        /** @var numeric-string $expectedAmount */
        $expectedAmount = $this->amount;
        if (bccomp($totalAllocated, $expectedAmount, 10) !== 0) {
            throw new AllocationReconciliationException(
                expectedAmount: $this->amount,
                actualAmount: $totalAllocated
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'id' => (string) $this->id,
            'family_id' => (string) $this->familyId,
            'author_user_id' => (string) $this->authorUserId,
            'store_id' => (string) $this->store->getId(),
            'store_name' => $this->store->getName(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'purchase_date' => $this->purchaseDate->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s'),
            'allocations' => array_map(
                fn (ExpenseAllocation $a): array => [
                    'id' => (string) $a->getId(),
                    'category_id' => (string) $a->getCategory()->getId(),
                    'category_name' => $a->getCategory()->getName(),
                    'amount' => $a->getAmount()->getAmount(),
                ],
                $this->allocations
            ),
        ];
    }
}
