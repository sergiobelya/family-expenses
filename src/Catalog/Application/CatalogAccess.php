<?php

namespace App\Catalog\Application;

use App\Core\Domain\Exception\NotFamilyMemberException;
use App\Core\Domain\Repository\FamilyMembershipInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class CatalogAccess
{
    public function __construct(
        private FamilyMembershipInterface $familyMembership,
        private FamilyCatalogLockInterface $lock
    ) {
    }

    public function assertMember(FamilyId $familyId, UserId $userId): void
    {
        if (!$this->familyMembership->isMember($familyId, $userId)) {
            throw NotFamilyMemberException::forFamily((string) $familyId, (string) $userId);
        }
    }

    public function lockFamilyForMemberMutation(FamilyId $familyId, UserId $userId): void
    {
        $this->assertMember($familyId, $userId);
        $this->lock->lockFamily($familyId);
        $this->assertMember($familyId, $userId);
    }
}
