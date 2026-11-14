<?php

namespace App\Tests\Unit\Family\Domain\Entity;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Enum\FamilyMemberStatus;
use PHPUnit\Framework\TestCase;

final class FamilyMemberTest extends TestCase
{
    public function testIsActiveReflectsMemberStatus(): void
    {
        $familyId = FamilyId::generate();
        $userId = UserId::generate();

        self::assertTrue((FamilyMember::join($familyId, $userId, status: FamilyMemberStatus::ACTIVE))->isActive());
        self::assertFalse((FamilyMember::join($familyId, $userId, status: FamilyMemberStatus::INACTIVE))->isActive());
    }
}
