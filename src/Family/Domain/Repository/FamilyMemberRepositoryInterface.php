<?php

namespace App\Family\Domain\Repository;

use App\Core\Domain\Repository\FamilyMembershipInterface;
use App\Family\Domain\Entity\FamilyMember;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

interface FamilyMemberRepositoryInterface extends FamilyMembershipInterface
{
    public function save(FamilyMember $member): void;

    public function isMember(FamilyId $familyId, UserId $userId): bool;

    public function findByFamilyAndUser(FamilyId $familyId, UserId $userId): ?FamilyMember;

    /**
     * @return FamilyMember[]
     */
    public function findByFamilyId(FamilyId $familyId): array;

    /**
     * @return FamilyMember[]
     */
    public function findByUserId(UserId $userId): array;
}
