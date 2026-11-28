<?php

namespace App\Expense\Domain\Repository;

use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Expense\Domain\Entity\ExpenseCategory;

interface CategoryRepositoryInterface
{
    public function findById(CategoryId $id, FamilyId $familyId): ?ExpenseCategory;
}
