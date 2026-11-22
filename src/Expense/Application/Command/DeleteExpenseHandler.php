<?php

namespace App\Expense\Application\Command;

use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Application\ExpenseAuthorizationService;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteExpenseHandler
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepository,
        private ExpenseAuthorizationService $authorization,
    ) {
    }

    public function __invoke(DeleteExpenseCommand $command): Expense
    {
        $this->authorization->assertMember($command->familyId, $command->userId);

        $expense = $this->assertExpenseExists($command->expenseId);
        $this->authorization->assertAuthor($expense, $command->userId);

        $expense->softDelete($command->userId);
        $this->expenseRepository->save($expense);

        return $expense;
    }

    private function assertExpenseExists(ExpenseId $expenseId): Expense
    {
        $expense = $this->expenseRepository->findById($expenseId);
        if ($expense === null) {
            throw new ExpenseNotFoundException((string) $expenseId);
        }
        return $expense;
    }
}
