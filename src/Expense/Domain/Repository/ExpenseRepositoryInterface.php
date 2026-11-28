<?php

namespace App\Expense\Domain\Repository;

use App\Expense\Domain\Entity\Expense;
use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;

interface ExpenseRepositoryInterface
{
    public function save(Expense $expense): void;

    public function findById(ExpenseId $id): ?Expense;

    /**
     * @return Expense[]
     */
    public function findByFamilyId(FamilyId $familyId): array;
}
