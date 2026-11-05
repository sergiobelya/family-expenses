<?php

namespace App\Tests\Unit\Application\Family\Command;

use App\Family\Application\Command\JoinFamilyByInvitationCommand;
use App\Family\Application\Command\JoinFamilyByInvitationHandler;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Entity\InvitationUse;
use App\Family\Domain\Exception\InvitationExpiredException;
use App\Family\Domain\Exception\InvitationLimitExceededException;
use App\Family\Domain\Exception\InvitationRevokedException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Family\Infrastructure\Security\InvitationTokenGenerator;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class JoinFamilyByInvitationHandlerTest extends TestCase
{
    private function createDependencies(): array
    {
        return [
            $this->createMock(UserRepositoryInterface::class),
            $this->createMock(FamilyRepositoryInterface::class),
            $this->createMock(FamilyMemberRepositoryInterface::class),
            $this->createMock(InvitationRepositoryInterface::class),
            $this->createMock(InvitationUseRepositoryInterface::class),
            new InvitationTokenGenerator(),
        ];
    }

    public function testJoinSuccess(): void
    {
        [$userRepo, $familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen] = $this->createDependencies();

        $user = new User(111);
        $family = new Family('My Family', new Timezone('Europe/Warsaw'), new CurrencyCode('PLN'), new CurrencyCode('PLN'));
        $rawToken = 'token123';
        $tokenHash = $tokenGen->hashToken($rawToken);

        $invitation = new Invitation($family->getId(), Uuid::v7(), $tokenHash, maxUses: 2);

        $userRepo->method('findById')->willReturn($user);
        $invRepo->method('findByTokenHash')->with($tokenHash)->willReturn($invitation);
        $familyRepo->method('findById')->with($family->getId())->willReturn($family);
        $memberRepo->method('isMember')->willReturn(false);
        $useRepo->method('countByInvitationId')->willReturn(0);

        $useRepo->expects($this->once())->method('save')->with($this->isInstanceOf(InvitationUse::class));
        $memberRepo->expects($this->once())->method('save')->with($this->isInstanceOf(FamilyMember::class));
        $userRepo->expects($this->once())->method('save')->with($user);

        $handler = new JoinFamilyByInvitationHandler($userRepo, $familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen);

        $result = $handler(new JoinFamilyByInvitationCommand($rawToken, $user->getId()));

        $this->assertSame($family, $result['family']);
        $this->assertFalse($result['already_member']);
        $this->assertEquals($family->getId(), $user->getActiveFamilyId());
    }

    public function testJoinWhenAlreadyMemberDoesNotConsumeUse(): void
    {
        [$userRepo, $familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen] = $this->createDependencies();

        $user = new User(111);
        $family = new Family('My Family', new Timezone('Europe/Warsaw'), new CurrencyCode('PLN'), new CurrencyCode('PLN'));
        $rawToken = 'token123';
        $tokenHash = $tokenGen->hashToken($rawToken);

        $invitation = new Invitation($family->getId(), Uuid::v7(), $tokenHash, maxUses: 1);

        $userRepo->method('findById')->willReturn($user);
        $invRepo->method('findByTokenHash')->with($tokenHash)->willReturn($invitation);
        $familyRepo->method('findById')->with($family->getId())->willReturn($family);
        $memberRepo->method('isMember')->with($family->getId(), $user->getId())->willReturn(true);

        // No new invitation use or member saved!
        $useRepo->expects($this->never())->method('save');
        $memberRepo->expects($this->never())->method('save');
        $userRepo->expects($this->once())->method('save')->with($user);

        $handler = new JoinFamilyByInvitationHandler($userRepo, $familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen);

        $result = $handler(new JoinFamilyByInvitationCommand($rawToken, $user->getId()));

        $this->assertSame($family, $result['family']);
        $this->assertTrue($result['already_member']);
    }

    public function testJoinRevokedThrowsException(): void
    {
        [$userRepo, $familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen] = $this->createDependencies();

        $user = new User(111);
        $family = new Family('My Family', new Timezone('Europe/Warsaw'), new CurrencyCode('PLN'), new CurrencyCode('PLN'));
        $rawToken = 'token123';
        $tokenHash = $tokenGen->hashToken($rawToken);

        $invitation = new Invitation($family->getId(), Uuid::v7(), $tokenHash);
        $invitation->revoke();

        $userRepo->method('findById')->willReturn($user);
        $invRepo->method('findByTokenHash')->willReturn($invitation);
        $familyRepo->method('findById')->willReturn($family);
        $memberRepo->method('isMember')->willReturn(false);

        $handler = new JoinFamilyByInvitationHandler($userRepo, $familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen);

        $this->expectException(InvitationRevokedException::class);
        $handler(new JoinFamilyByInvitationCommand($rawToken, $user->getId()));
    }

    public function testJoinLimitExceededThrowsException(): void
    {
        [$userRepo, $familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen] = $this->createDependencies();

        $user = new User(111);
        $family = new Family('My Family', new Timezone('Europe/Warsaw'), new CurrencyCode('PLN'), new CurrencyCode('PLN'));
        $rawToken = 'token123';
        $tokenHash = $tokenGen->hashToken($rawToken);

        $invitation = new Invitation($family->getId(), Uuid::v7(), $tokenHash, maxUses: 1);

        $userRepo->method('findById')->willReturn($user);
        $invRepo->method('findByTokenHash')->willReturn($invitation);
        $familyRepo->method('findById')->willReturn($family);
        $memberRepo->method('isMember')->willReturn(false);
        $useRepo->method('countByInvitationId')->willReturn(1); // Already 1 use!

        $handler = new JoinFamilyByInvitationHandler($userRepo, $familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen);

        $this->expectException(InvitationLimitExceededException::class);
        $handler(new JoinFamilyByInvitationCommand($rawToken, $user->getId()));
    }
}
