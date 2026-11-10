<?php

namespace App\Family\Domain\Repository;

use App\Family\Domain\Entity\Family;
use App\Core\Domain\ValueObject\FamilyId;

interface FamilyRepositoryInterface
{
    public function save(Family $family): void;

    public function findById(FamilyId $id): ?Family;

    /**
     * @param FamilyId[] $ids
     * @return Family[]
     */
    public function findByIds(array $ids): array;
}
