<?php

namespace App\Tests\Unit\Application\Expense\Command;

use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\Exception\NotFamilyMemberException;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Application\Command\AddExpenseCommand;
use App\Expense\Application\Command\AddExpenseHandler;
use App\Expense\Application\Command\AllocationItem;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Application\Validation\ExpenseAmountValidator;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\ExpenseCategory;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\Exception\CategoryNotFoundException;
use App\Expense\Domain\Exception\StoreNotFoundException;
use App\Expense\Domain\Repository\CategoryRepositoryInterface;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Repository\StoreRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddExpenseHandler::class)]
final class AddExpenseHandlerTest extends TestCase
{
    private ExpenseRepositoryInterface&MockObject $expenseRepository;
    private StoreRepositoryInterface&MockObject $storeRepository;
    private CategoryRepositoryInterface&MockObject $categoryRepository;
    private MockObject $authorization;
    private AddExpenseHandler $handler;

    private FamilyId $familyId;
    private UserId $userId;
    private StoreId $storeId;
    private CategoryId $categoryId;
    private ExpenseStore $store;
    private ExpenseCategory $category;

    protected function setUp(): void
    {
        $this->expenseRepository = $this->createMock(ExpenseRepositoryInterface::class);
        $this->storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->authorization = $this->createMock(ExpenseAuthorizationService::class);

        $this->handler = new AddExpenseHandler(
            $this->expenseRepository,
            $this->storeRepository,
            $this->categoryRepository,
            new ExpenseAmountValidator(),
            $this->authorization
        );

        $this->familyId = FamilyId::generate();
        $this->userId = UserId::generate();
        $this->storeId = StoreId::generate();
        $this->categoryId = CategoryId::generate();

        $this->store = new ExpenseStore($this->storeId, 'Aldi', $this->familyId);
        $this->category = new ExpenseCategory($this->categoryId, 'Food', $this->familyId);
    }

    #[Test]
    public function testCreateExpenseSuccessfully(): void
    {
        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->with($this->storeId, $this->familyId)
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->with($this->categoryId, $this->familyId)
            ->willReturn($this->category);

        $this->expenseRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Expense::class));

        $command = new AddExpenseCommand(
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.50',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '100.50'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $expense = ($this->handler)($command);

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertSame($this->familyId, $expense->getFamilyId());
        $this->assertSame($this->userId, $expense->getAuthorUserId());
        $this->assertSame('Aldi', $expense->getStore()->getName());
        $this->assertCount(1, $expense->getAllocations());
    }

    #[Test]
    public function testCreateExpenseWithMultipleAllocations(): void
    {
        $category2 = new ExpenseCategory(CategoryId::generate(), 'Transport', $this->familyId);

        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($this->category, $category2);

        $this->expenseRepository->expects($this->once())
            ->method('save');

        $command = new AddExpenseCommand(
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '60.00'),
                new AllocationItem($category2->getId(), '40.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $expense = ($this->handler)($command);

        $this->assertCount(2, $expense->getAllocations());
    }

    #[Test]
    public function testRejectsNonMember(): void
    {
        $this->authorization->expects($this->once())
            ->method('assertMember')
            ->willThrowException(NotFamilyMemberException::forFamily((string) $this->familyId, (string) $this->userId));

        $command = $this->createValidCommand();

        $this->expectException(NotFamilyMemberException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsStoreNotFound(): void
    {
        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $command = $this->createValidCommand();

        $this->expectException(StoreNotFoundException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsCategoryNotFound(): void
    {
        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $command = $this->createValidCommand();

        $this->expectException(CategoryNotFoundException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsUnsupportedCurrency(): void
    {
        $command = new AddExpenseCommand(
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.50',
            currency: 'XXX',
            allocations: [
                new AllocationItem($this->categoryId, '100.50'),
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
        $command = new AddExpenseCommand(
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
    public function testRejectsAmountAboveConfiguredMaximum(): void
    {
        $this->storeRepository->expects($this->never())->method('findById');
        $this->categoryRepository->expects($this->never())->method('findById');
        $this->expenseRepository->expects($this->never())->method('save');

        $command = new AddExpenseCommand(
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100000001',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '100000001'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_too_large');
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsFuturePurchaseDate(): void
    {
        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->category);

        $command = new AddExpenseCommand(
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.50',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '100.50'),
            ],
            purchaseDate: new DateTimeImmutable('2099-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: purchase_date_in_future');
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsEmptyAllocations(): void
    {
        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $command = new AddExpenseCommand(
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.50',
            currency: 'PLN',
            allocations: [],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(\App\Expense\Domain\Exception\AllocationReconciliationException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsMismatchedAllocationSum(): void
    {
        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->category);

        $command = new AddExpenseCommand(
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
    public function testRejectsExcessAllocationSum(): void
    {
        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->category);

        $command = new AddExpenseCommand(
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '150.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(\App\Expense\Domain\Exception\AllocationReconciliationException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function testRejectsNonPositiveAllocation(): void
    {
        $this->storeRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->store);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->willReturn($this->category);

        $command = new AddExpenseCommand(
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.00',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '-10.00'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('An invalid value was provided for a domain field: money_amount_not_positive');
        ($this->handler)($command);
    }

    private function createValidCommand(): AddExpenseCommand
    {
        return new AddExpenseCommand(
            familyId: $this->familyId,
            userId: $this->userId,
            storeId: $this->storeId,
            amount: '100.50',
            currency: 'PLN',
            allocations: [
                new AllocationItem($this->categoryId, '100.50'),
            ],
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
        );
    }
}
