<?php

namespace App\Catalog\Application\Query;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class SearchCategoriesQuery
{
    public function __construct(
        public FamilyId $familyId,
        public UserId $requestedByUserId,
        public string $query
    ) {
    }
}
