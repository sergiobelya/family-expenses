<?php

namespace App\Core\Domain\Repository;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

interface FamilyMembershipInterface
{
    public function isMember(FamilyId $familyId, UserId $userId): bool;
}
