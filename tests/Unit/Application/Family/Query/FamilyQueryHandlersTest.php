<?php

namespace App\Tests\Unit\Application\Family\Query;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Family\Application\Query\GetFamilyInvitationsHandler;
use App\Family\Application\Query\GetFamilyInvitationsQuery;
use App\Family\Application\Query\GetFamilyMembersHandler;
use App\Family\Application\Query\GetFamilyMembersQuery;
use App\Family\Application\Query\GetFamilySettingsHandler;
use App\Family\Application\Query\GetFamilySettingsQuery;
use App\Family\Application\Query\GetUserFamiliesHandler;
use App\Family\Application\Query\GetUserFamiliesQuery;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class FamilyQueryHandlersTest extends TestCase
{
    public function testGetFamilyMembers(): void
    {
        $familyId = Uuid::v7();
        $userId = Uuid::v7();
        $members = [new FamilyMember($familyId, $userId)];

        $repo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $repo->method('isMember')->with($familyId, $userId)->willReturn(true);
        $repo->method('findByFamilyId')->with($familyId)->willReturn($members);

        $handler = new GetFamilyMembersHandler($repo);
        $result = $handler(new GetFamilyMembersQuery($familyId, $userId));

        $this->assertSame($members, $result);
    }

    public function testGetFamilyInvitations(): void
    {
        $familyId = Uuid::v7();
        $userId = Uuid::v7();
        $invitations = [Invitation::create($familyId, $userId, 'hash', null, 1)];

        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->method('isMember')->with($familyId, $userId)->willReturn(true);

        $invRepo = $this->createMock(InvitationRepositoryInterface::class);
        $invRepo->method('findByFamilyId')->with($familyId)->willReturn($invitations);

        $handler = new GetFamilyInvitationsHandler($memberRepo, $invRepo);
        $result = $handler(new GetFamilyInvitationsQuery($familyId, $userId));

        $this->assertSame($invitations, $result);
    }

    public function testGetFamilySettings(): void
    {
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $userId = Uuid::v7();

        $familyRepo = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepo->method('findById')->with($family->getId())->willReturn($family);

        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->method('isMember')->with($family->getId(), $userId)->willReturn(true);

        $handler = new GetFamilySettingsHandler($familyRepo, $memberRepo);
        $result = $handler(new GetFamilySettingsQuery($family->getId(), $userId));

        $this->assertSame($family, $result);
    }

    public function testGetUserFamilies(): void
    {
        $userId = Uuid::v7();
        $family = Family::create('Smiths', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $member = new FamilyMember($family->getId(), $userId);

        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->method('findByUserId')->with($userId)->willReturn([$member]);

        $familyRepo = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepo->method('findByIds')->with([$family->getId()])->willReturn([$family]);

        $handler = new GetUserFamiliesHandler($memberRepo, $familyRepo);
        $result = $handler(new GetUserFamiliesQuery($userId));

        $this->assertSame([$family], $result);
    }
}
