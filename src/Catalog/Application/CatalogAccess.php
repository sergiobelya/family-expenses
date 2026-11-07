<?php

namespace App\Catalog\Application;

use App\Family\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CatalogAccess
{
    public function __construct(
        private FamilyMemberRepositoryInterface $familyMembers,
        private CatalogRepositoryInterface $catalog
    ) {
    }

    public function assertMember(Uuid $familyId, Uuid $userId): void
    {
        if (!$this->familyMembers->isMember($familyId, $userId)) {
            throw NotFamilyMemberException::forFamily((string) $familyId, (string) $userId);
        }
    }

    public function lockFamilyForMemberMutation(Uuid $familyId, Uuid $userId): void
    {
        $this->assertMember($familyId, $userId);
        $this->catalog->lockFamily($familyId);
        $this->assertMember($familyId, $userId);
    }
}
