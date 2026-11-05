<?php

namespace App\Family\Domain\Repository;

use App\Family\Domain\Entity\Family;
use Symfony\Component\Uid\Uuid;

interface FamilyRepositoryInterface
{
    public function save(Family $family): void;

    public function findById(Uuid $id): ?Family;

    /**
     * @param Uuid[] $ids
     * @return Family[]
     */
    public function findByIds(array $ids): array;
}
