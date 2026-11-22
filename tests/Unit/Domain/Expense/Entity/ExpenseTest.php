<?php

namespace App\Tests\Unit\Domain\Expense\Entity;

use App\Core\Domain\ValueObject\AllocationId;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\MoneyAmount;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\ExpenseAllocation;
use App\Expense\Domain\Event\ExpenseCreated;
use App\Expense\Domain\Event\ExpenseDeleted;
use App\Expense\Domain\Event\ExpenseEdited;
use App\Expense\Domain\Exception\AllocationReconciliationException;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class ExpenseTest extends TestCase
{
    private DateTimeImmutable $now;
    private FamilyId $familyId;
    private UserId $userId;
    private StoreId $storeId;
    private MoneyAmount $amount;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-01-15 10:00:00', new DateTimeZone('UTC'));
        $this->familyId = FamilyId::generate();
        $this->userId = UserId::generate();
        $this->storeId = StoreId::generate();
        $this->amount = MoneyAmount::fromDecimal('100.50', CurrencyCode::fromString('PLN'));
    }

    /** @return list<ExpenseAllocation> */
    private function createValidAllocations(): array
    {
        return [
            ExpenseAllocation::create(
                CategoryId::generate(),
                MoneyAmount::fromDecimal('60.00', CurrencyCode::fromString('PLN'))
            ),
            ExpenseAllocation::create(
                CategoryId::generate(),
                MoneyAmount::fromDecimal('40.50', CurrencyCode::fromString('PLN'))
            ),
        ];
    }

    public function testCreateSetsProperties(): void
    {
        $allocations = $this->createValidAllocations();
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $allocations,
            now: $this->now
        );

        $this->assertInstanceOf(ExpenseId::class, $expense->getId());
        $this->assertTrue($expense->getFamilyId()->equals($this->familyId));
        $this->assertTrue($expense->getAuthorUserId()->equals($this->userId));
        $this->assertTrue($expense->getStoreId()->equals($this->storeId));
        $this->assertTrue($expense->getAmount()->equals($this->amount));
        $this->assertSame($this->now, $expense->getPurchaseDate());
        $this->assertNull($expense->getDeletedAt());
        $this->assertFalse($expense->isDeleted());
        $this->assertCount(2, $expense->getAllocations());
    }

    public function testCreateWithExplicitId(): void
    {
        $expectedId = ExpenseId::generate();
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            id: $expectedId,
            now: $this->now
        );

        $this->assertTrue($expense->getId()->equals($expectedId));
    }

    public function testCreateRecordsExpenseCreatedEvent(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );

        $events = array_values(iterator_to_array($expense->releaseEvents()));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ExpenseCreated::class, $events[0]);
        $this->assertTrue($events[0]->getExpenseId()->equals($expense->getId()));
        $familyIdFromEvent = $events[0]->getFamilyId();
        $this->assertNotNull($familyIdFromEvent);
        $this->assertTrue($familyIdFromEvent->equals($this->familyId));
        $this->assertTrue($events[0]->getActorUserId()->equals($this->userId));
        $this->assertNotNull($events[0]->getAfterData());
        $this->assertNull($events[0]->getBeforeData());
    }

    public function testCreateRejectsMismatchedAllocations(): void
    {
        $allocations = [
            ExpenseAllocation::create(
                CategoryId::generate(),
                MoneyAmount::fromDecimal('50.00', CurrencyCode::fromString('PLN'))
            ),
        ];

        $this->expectException(AllocationReconciliationException::class);
        Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $allocations,
            now: $this->now
        );
    }

    public function testCreateRejectsExcessAllocations(): void
    {
        $allocations = [
            ExpenseAllocation::create(
                CategoryId::generate(),
                MoneyAmount::fromDecimal('80.00', CurrencyCode::fromString('PLN'))
            ),
            ExpenseAllocation::create(
                CategoryId::generate(),
                MoneyAmount::fromDecimal('80.00', CurrencyCode::fromString('PLN'))
            ),
        ];

        $this->expectException(AllocationReconciliationException::class);
        Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $allocations,
            now: $this->now
        );
    }

    public function testCreateWithSingleAllocationMatchingTotal(): void
    {
        $allocations = [
            ExpenseAllocation::create(
                CategoryId::generate(),
                $this->amount
            ),
        ];

        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $allocations,
            now: $this->now
        );

        $this->assertCount(1, $expense->getAllocations());
    }

    public function testEditUpdatesProperties(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );
        iterator_to_array($expense->releaseEvents());

        $newStoreId = StoreId::generate();
        $newAmount = MoneyAmount::fromDecimal('200.00', CurrencyCode::fromString('PLN'));
        $newDate = new DateTimeImmutable('2026-02-01 12:00:00', new DateTimeZone('UTC'));
        $newAllocations = [
            ExpenseAllocation::create(
                CategoryId::generate(),
                MoneyAmount::fromDecimal('200.00', CurrencyCode::fromString('PLN'))
            ),
        ];
        $newNow = new DateTimeImmutable('2026-02-01 13:00:00', new DateTimeZone('UTC'));

        $expense->edit(
            storeId: $newStoreId,
            amount: $newAmount,
            purchaseDate: $newDate,
            allocations: $newAllocations,
            actorUserId: $this->userId,
            now: $newNow
        );

        $this->assertTrue($expense->getStoreId()->equals($newStoreId));
        $this->assertTrue($expense->getAmount()->equals($newAmount));
        $this->assertSame($newDate, $expense->getPurchaseDate());
        $this->assertCount(1, $expense->getAllocations());
        $this->assertSame($newNow, $expense->getUpdatedAt());
    }

    public function testEditRecordsExpenseEditedEvent(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );
        iterator_to_array($expense->releaseEvents());

        $newAllocations = [
            ExpenseAllocation::create(
                CategoryId::generate(),
                MoneyAmount::fromDecimal('100.50', CurrencyCode::fromString('PLN'))
            ),
        ];

        $expense->edit(
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $newAllocations,
            actorUserId: $this->userId,
            now: $this->now
        );

        $events = array_values(iterator_to_array($expense->releaseEvents()));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ExpenseEdited::class, $events[0]);
        $this->assertNotNull($events[0]->getBeforeData());
        $this->assertNotNull($events[0]->getAfterData());
    }

    public function testEditRejectsMismatchedAllocations(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );
        iterator_to_array($expense->releaseEvents());

        $badAllocations = [
            ExpenseAllocation::create(
                CategoryId::generate(),
                MoneyAmount::fromDecimal('10.00', CurrencyCode::fromString('PLN'))
            ),
        ];

        $this->expectException(AllocationReconciliationException::class);
        $expense->edit(
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $badAllocations,
            actorUserId: $this->userId,
            now: $this->now
        );
    }

    public function testSoftDeleteSetsDeletedAt(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );
        iterator_to_array($expense->releaseEvents());

        $deletedAt = new DateTimeImmutable('2026-03-01 09:00:00', new DateTimeZone('UTC'));
        $expense->softDelete($this->userId, $deletedAt);

        $this->assertTrue($expense->isDeleted());
        $this->assertSame($deletedAt, $expense->getDeletedAt());
    }

    public function testSoftDeleteRecordsExpenseDeletedEvent(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );
        iterator_to_array($expense->releaseEvents());

        $expense->softDelete($this->userId, $this->now);

        $events = array_values(iterator_to_array($expense->releaseEvents()));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ExpenseDeleted::class, $events[0]);
        $this->assertNotNull($events[0]->getBeforeData());
        $this->assertNull($events[0]->getAfterData());
    }

    public function testSoftDeleteIsIdempotent(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );
        iterator_to_array($expense->releaseEvents());

        $firstDelete = new DateTimeImmutable('2026-03-01 09:00:00', new DateTimeZone('UTC'));
        $expense->softDelete($this->userId, $firstDelete);
        iterator_to_array($expense->releaseEvents());

        $secondDelete = new DateTimeImmutable('2026-03-02 09:00:00', new DateTimeZone('UTC'));
        $expense->softDelete($this->userId, $secondDelete);

        $this->assertSame($firstDelete, $expense->getDeletedAt());
        $events = array_values(iterator_to_array($expense->releaseEvents()));
        $this->assertCount(0, $events);
    }

    public function testAllocationsHaveGeneratedIds(): void
    {
        $allocations = $this->createValidAllocations();
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $allocations,
            now: $this->now
        );

        foreach ($expense->getAllocations() as $allocation) {
            $this->assertInstanceOf(AllocationId::class, $allocation->getId());
        }
    }

    public function testSetAllocationsReplacesAll(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );

        $newAllocation = ExpenseAllocation::create(
            CategoryId::generate(),
            MoneyAmount::fromDecimal('100.50', CurrencyCode::fromString('PLN'))
        );
        $expense->setAllocations([$newAllocation]);

        $this->assertCount(1, $expense->getAllocations());
    }

    public function testCreatedAtAndUpdatedAtSetOnCreate(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );

        $this->assertSame($this->now, $expense->getCreatedAt());
        $this->assertSame($this->now, $expense->getUpdatedAt());
    }

    public function testGetAmountReturnsMoneyAmount(): void
    {
        $expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            storeId: $this->storeId,
            amount: $this->amount,
            purchaseDate: $this->now,
            allocations: $this->createValidAllocations(),
            now: $this->now
        );

        $retrievedAmount = $expense->getAmount();
        $this->assertInstanceOf(MoneyAmount::class, $retrievedAmount);
        $this->assertTrue($retrievedAmount->equals($this->amount));
        $this->assertTrue($retrievedAmount->getCurrency()->equals(CurrencyCode::fromString('PLN')));
    }
}
