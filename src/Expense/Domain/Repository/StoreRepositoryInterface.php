<?php

namespace App\Expense\Domain\Repository;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Expense\Domain\Entity\ExpenseStore;

interface StoreRepositoryInterface
{
    public function findById(StoreId $id, FamilyId $familyId): ?ExpenseStore;

    /** @return list<StoreId> */
    public function findIdsByLastPurchase(FamilyId $familyId): array;
}
