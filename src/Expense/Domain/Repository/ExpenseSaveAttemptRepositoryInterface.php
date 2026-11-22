<?php

namespace App\Expense\Domain\Repository;

use App\Expense\Domain\Entity\ExpenseSaveAttempt;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Domain\ValueObject\ExpenseSaveAttemptId;

interface ExpenseSaveAttemptRepositoryInterface
{
    public function save(ExpenseSaveAttempt $attempt): void;

    public function findById(ExpenseSaveAttemptId $id, FamilyId $familyId): ?ExpenseSaveAttempt;

    public function findByIdempotencyKey(string $idempotencyKey, FamilyId $familyId): ?ExpenseSaveAttempt;

    public function findLastPendingByUserAndFamily(UserId $userId, FamilyId $familyId): ?ExpenseSaveAttempt;
}
