<?php

namespace App\Tests\Unit\Application\Expense\Command;

use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Application\Command\DeleteExpenseCommand;
use App\Expense\Application\Command\DeleteExpenseHandler;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\ExpenseAllocation;
use App\Expense\Domain\Entity\ExpenseCategory;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Core\Domain\Exception\NotFamilyMemberException;
use App\Expense\Domain\Exception\ExpenseNotAuthorException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteExpenseHandler::class)]
final class DeleteExpenseHandlerTest extends TestCase
{
    private ExpenseRepositoryInterface&MockObject $expenseRepository;
    /** @var MockObject&ExpenseAuthorizationService @phpstan-ignore property.unresolvableType */
    private MockObject $authorization;
    private DeleteExpenseHandler $handler;

    private FamilyId $familyId;
    private UserId $userId;
    private UserId $otherUserId;
    private Expense $expense;

    protected function setUp(): void
    {
        $this->expenseRepository = $this->createMock(ExpenseRepositoryInterface::class);
        $this->authorization = $this->createMock(ExpenseAuthorizationService::class);

        $this->handler = new DeleteExpenseHandler(
            $this->expenseRepository,
            $this->authorization
        );

        $this->familyId = FamilyId::generate();
        $this->userId = UserId::generate();
        $this->otherUserId = UserId::generate();

        $store = new ExpenseStore(
            \App\Core\Domain\ValueObject\StoreId::generate(),
            'Aldi',
            $this->familyId
        );
        $category = new ExpenseCategory(
            \App\Core\Domain\ValueObject\CategoryId::generate(),
            'Food',
            $this->familyId
        );

        $this->expense = Expense::create(
            familyId: $this->familyId,
            authorUserId: $this->userId,
            store: $store,
            amount: \App\Core\Domain\ValueObject\MoneyAmount::fromDecimal('100.00', \App\Core\Domain\ValueObject\CurrencyCode::fromString('PLN')),
            purchaseDate: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')),
            allocations: [
                new ExpenseAllocation(
                    $category,
                    \App\Core\Domain\ValueObject\MoneyAmount::fromDecimal('100.00', \App\Core\Domain\ValueObject\CurrencyCode::fromString('PLN'))
                ),
            ],
            now: new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC'))
        );
    }

    #[Test]
    public function testDeleteExpenseSuccessfully(): void
    {
        $this->expenseRepository->expects($this->once())
            ->method('findById')
            ->with($this->expense->getId())
            ->willReturn($this->expense);

        $this->expenseRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Expense::class));

        $command = new DeleteExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
        );

        $deletedExpense = ($this->handler)($command);

        $this->assertTrue($deletedExpense->isDeleted());
        $this->assertNotNull($deletedExpense->getDeletedAt());
    }

    #[Test]
    public function testRejectsNonMember(): void
    {
        $this->authorization->expects($this->once())
            ->method('assertMember')
            ->willThrowException(NotFamilyMemberException::forFamily((string) $this->familyId, (string) $this->userId));

        $command = new DeleteExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->userId,
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

        $command = new DeleteExpenseCommand(
            expenseId: ExpenseId::generate(),
            familyId: $this->familyId,
            userId: $this->userId,
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

        $command = new DeleteExpenseCommand(
            expenseId: $this->expense->getId(),
            familyId: $this->familyId,
            userId: $this->otherUserId,
        );

        $this->expectException(ExpenseNotAuthorException::class);
        ($this->handler)($command);
    }
}
