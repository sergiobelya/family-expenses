<?php

namespace App\Catalog\Application;

use App\Core\Domain\ValueObject\FamilyId;

interface FamilyCatalogLockInterface
{
    public function lockFamily(FamilyId $familyId): void;
}
