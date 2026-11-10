<?php

namespace App\Catalog\Application\Query;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class ListStoresQuery
{
    public function __construct(
        public FamilyId $familyId,
        public UserId $requestedByUserId
    ) {
    }
}
