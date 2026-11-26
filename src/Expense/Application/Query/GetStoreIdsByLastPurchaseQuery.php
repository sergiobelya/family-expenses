<?php

namespace App\Expense\Application\Query;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class GetStoreIdsByLastPurchaseQuery
{
    public function __construct(
        public FamilyId $familyId,
        public UserId $requestedByUserId,
    ) {
    }
}
