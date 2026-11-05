<?php

namespace App\Family\Domain\Repository;

use App\Family\Domain\Entity\FamilyMember;
use Symfony\Component\Uid\Uuid;

interface FamilyMemberRepositoryInterface
{
    public function save(FamilyMember $member): void;

    public function isMember(Uuid $familyId, Uuid $userId): bool;

    public function findByFamilyAndUser(Uuid $familyId, Uuid $userId): ?FamilyMember;

    /**
     * @return FamilyMember[]
     */
    public function findByFamilyId(Uuid $familyId): array;

    /**
     * @return FamilyMember[]
     */
    public function findByUserId(Uuid $userId): array;
}
