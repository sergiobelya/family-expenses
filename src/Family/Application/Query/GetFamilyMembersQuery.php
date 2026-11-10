<?php

namespace App\Family\Application\Query;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class GetFamilyMembersQuery
{
    public function __construct(
        public FamilyId $familyId,
        public UserId $requestedByUserId
    ) {
    }
}
