<?php

namespace App\Tests\Unit\Application\Identity\Event;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\FamilyMemberId;
use App\Core\Domain\ValueObject\UserId;
use App\Family\Domain\Event\MemberJoined;
use App\Identity\Application\Event\SwitchActiveFamilyOnMemberJoinedHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class SwitchActiveFamilyOnMemberJoinedHandlerTest extends TestCase
{
    public function testSwitchesActiveFamilyWhenUserExists(): void
    {
        $userId = new UserId(Uuid::v7());
        $familyId = new FamilyId(Uuid::v7());
        $memberId = new FamilyMemberId(Uuid::v7());

        $user = new User(telegramUserId: 123456);

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);
        $userRepo->expects($this->once())
            ->method('save')
            ->with($user);

        $handler = new SwitchActiveFamilyOnMemberJoinedHandler($userRepo);

        $event = new MemberJoined(
            memberId: $memberId,
            familyId: $familyId,
            userId: $userId
        );
        $handler($event);

        $this->assertSame($familyId, $user->getActiveFamilyId());
    }

    public function testDoesNothingWhenUserNotFound(): void
    {
        $userId = new UserId(Uuid::v7());
        $familyId = new FamilyId(Uuid::v7());
        $memberId = new FamilyMemberId(Uuid::v7());

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);
        $userRepo->expects($this->never())
            ->method('save');

        $handler = new SwitchActiveFamilyOnMemberJoinedHandler($userRepo);

        $event = new MemberJoined(
            memberId: $memberId,
            familyId: $familyId,
            userId: $userId
        );
        $handler($event);
    }
}
