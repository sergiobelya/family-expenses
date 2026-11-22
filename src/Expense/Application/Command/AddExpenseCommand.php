<?php

namespace App\Expense\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;

final readonly class AddExpenseCommand
{
    /**
     * @param AllocationItem[] $allocations
     */
    public function __construct(
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
