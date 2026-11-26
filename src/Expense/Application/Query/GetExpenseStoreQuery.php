<?php

namespace App\Expense\Application\Query;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;

final readonly class GetExpenseStoreQuery
{
    public function __construct(
        public FamilyId $familyId,
        public UserId $requestedByUserId,
        public StoreId $storeId,
    ) {
    }
}
