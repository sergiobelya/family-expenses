<?php

namespace App\Expense\Domain\Repository;

use App\Expense\Domain\ExpenseDraft;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

interface ExpenseDraftRepositoryInterface
{
    public function find(UserId $userId, FamilyId $familyId): ?ExpenseDraft;

    public function save(ExpenseDraft $draft): void;

    public function remove(UserId $userId, FamilyId $familyId): void;
}
