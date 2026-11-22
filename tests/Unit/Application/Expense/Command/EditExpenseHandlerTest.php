<?php

namespace App\Tests\Unit\Application\Expense\Command;

use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Application\Command\AllocationItem;
use App\Expense\Application\Command\EditExpenseCommand;
use App\Expense\Application\Command\EditExpenseHandler;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\ExpenseAllocation;
use App\Expense\Domain\Entity\ExpenseCategory;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\Exception\CategoryNotFoundException;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Exception\ExpenseNotAuthorException;
use App\Expense\Domain\Exception\StoreNotFoundException;
use App\Expense\Domain\Repository\CategoryRepositoryInterface;
use App\Core\Domain\Exception\NotFamilyMemberException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Repository\StoreRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditExpenseHandler::class)]
final class EditExpenseHandlerTest extends TestCase
{
    private ExpenseRepositoryInterface&MockObject $expenseRepository;
    private StoreRepositoryInterface&MockObject $storeRepository;
    private CategoryRepositoryInterface&MockObject $categoryRepository;
    /** @var MockObject&ExpenseAuthorizationService @phpstan-ignore property.unresolvableType */
    private MockObject $authorization;
    private EditExpenseHandler $handler;

    private FamilyId $familyId;
    private UserId $userId;
    private UserId $otherUserId;
    private StoreId $storeId;
    private CategoryId $categoryId;
    private ExpenseStore $store;
    private ExpenseCategory $category;
    private Expense $expense;

    protected function setUp(): void
    {
        $this->expenseRepository = $this->createMock(ExpenseRepositoryInterface::class);
        $this->storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->authorization = $this->createMock(ExpenseAuthorizationService::class);

        $this->handler = new EditExpenseHandler(
            $this->expenseRepository,
            $this->storeRepository,
            $this->categoryRepository,
            $this->authorization
        );

        $this->familyId = FamilyId::generate();
        $this->userId = UserId::generate();
        $this->otherUserId = UserId::generate();
        $this->storeId = StoreId::generate();
        $this->categoryId = CategoryId::generate();

        $this->store = new ExpenseStore($this->storeId, 'Aldi', $this->familyId);
        $this->category = new ExpenseCategory($this->categoryId, 'Food', $this->familyId);

        $this->expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            store: $this->store,
            amount: \App\Core\Domain\ValueObject\MoneyAmount::fromDecimal('100.00', \App\Core\Domain\ValueObject\CurrencyCode::fromString('PLN')),
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
            allocations: [
                new ExpenseAllocation($this->category, \App\Core\Domain\ValueObject\MoneyAmount::fromDecimal('100.00', \App\Core\Domain\ValueObject\CurrencyCode::fromString('PLN'))),
            ],
            now: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC'))
        );
    }

    #[Test]
    public function testEditExpenseSuccessfully(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->with($this->expense->getId())
            ->willReturn($this->expense);

        $newStore = new ExpenseStore(StoreId::generate(), 'Lidl', $this->familyId);
        $newCategory = new ExpenseCategory(CategoryId::generate(), 'Drinks', $this->familyId);

        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->with($newStore->getId(), $this->familyId)
            ->willReturn($newStore);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->with($newCategory->getId(), $this->familyId)
            ->willReturn($newCategory);

        $this->expenseRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Expense::class));

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $newStore->getId(),
            amount: '200.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($newCategory->getId(), '200.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-02-01 10:00:00', new DateTimeZone('UTC')),
        );

        $editedExpense = ($this->handler)($command);

        $this->assertInstanceOf(Expense::class, $editedExpense);
        $this->assertSame('Lidl', $editedExpense->getStore()->getName());
        $this->assertCount(1, $editedExpense->getAllocations());
    }

    #[Test]
    public function testRejectsNonMember(): void
    {
        $this->authorization->expects($this->once())
            ->method('assertMember')
            ->willThrowException(NotFamilyMemberException::forFamily((string) $this->familyId, (string) $this->userId));

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '100.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(NotFamilyMemberException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsExpenseNotFound(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $command = new EditExpenseCommand(
            expenseId: ExpenseId::generate(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '100.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(ExpenseNotFoundException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsNonAuthor(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->with($this->expense->getId())
            ->willReturn($this->expense);

        $this->authorization->expects($this->once())
            ->method('assertAuthor')
            ->willThrowException(new ExpenseNotAuthorException((string) $this->expense->getId(), (string) $this->otherUserId));

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->otherUserId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '100.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(ExpenseNotAuthorException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsStoreNotFound(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->expense);

        $unknownStoreId = StoreId::generate();
        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->with($unknownStoreId, $this->familyId)
            ->willReturn(null);

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $unknownStoreId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '100.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(StoreNotFoundException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsCategoryNotFound(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->expense);

        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $unknownCategoryId = CategoryId::generate();
        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->with($unknownCategoryId, $this->familyId)
            ->willReturn(null);

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($unknownCategoryId, '100.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(CategoryNotFoundException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsUnsupportedCurrency(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->expense);

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'XXX',
            allocations: [
                new AllocationItem($this->categoryId, '100.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: currency_code_unsupported');
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsZeroAmount(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->expense);

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '0.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '0.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsFutureDate(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->expense);

        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->category);

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '100.00'),
            ],
            purchaseDate: new DateTimeImmutable('2099-01-01 00:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: purchase_date_in_future');
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsMismatchedAllocations(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->expense);

        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->category);

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '50.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(\App\Expense\Domain\Exception\AllocationReconciliationException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testEditWithMultipleAllocations(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->expense);

        $category2 = new ExpenseCategory(CategoryId::generate(), 'Transport', $this->familyId);

        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($this->category, $category2);

        $this->expenseRepository->expects($this->once())
            ->method('save');

        $command = new EditExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '200.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '120.00'),
                new AllocationItem($category2->getId(), '80.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-02-01 10:00:00', new DateTimeZone('UTC')),
        );

        $editedExpense = ($this->handler)($command);

        $this->assertCount(2, $editedExpense->getAllocations());
    }
}
