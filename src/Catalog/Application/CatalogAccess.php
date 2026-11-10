<?php

namespace App\Catalog\Application;

use App\Family\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Catalog\Domain\Repository\FamilyCatalogLockInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final class CatalogAccess
{
    public function __construct(
        private FamilyMemberRepositoryInterface $familyMembers,
        private FamilyCatalogLockInterface $catalog
    ) {
    }

    public function assertMember(FamilyId $familyId, UserId $userId): void
    {
        if (!$this->familyMembers->isMember($familyId, $userId)) {
            throw NotFamilyMemberException::forFamily((string) $familyId, (string) $userId);
        }
    }

    public function lockFamilyForMemberMutation(FamilyId $familyId, UserId $userId): void
    {
        $this->assertMember($familyId, $userId);
        $this->catalog->lockFamily($familyId);
        $this->assertMember($familyId, $userId);
    }
}
