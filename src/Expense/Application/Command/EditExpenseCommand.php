<?php

namespace App\Expense\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Domain\ValueObject\ExpenseId;
use DateTimeImmutable;

final readonly class EditExpenseCommand
{
    /**
     * @param AllocationItem[] $allocations
     */
    public function __construct(
        public ExpenseId $expenseId,
        public FamilyId $familyId,
        public UserId $userId,
        public StoreId $storeId,
        public string $amount,
        public string $currency,
        public array $allocations,
        public DateTimeImmutable $purchaseDate,
    ) {
    }
}
