<?php

namespace App\Catalog\Domain\Repository;

use App\Core\Domain\ValueObject\FamilyId;

interface FamilyCatalogLockInterface
{
    public function lockFamily(FamilyId $familyId): void;
}
