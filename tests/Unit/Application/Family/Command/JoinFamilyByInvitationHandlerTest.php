<?php

namespace App\Tests\Unit\Application\Family\Command;

use App\Family\Application\Command\JoinFamilyByInvitationCommand;
use App\Family\Application\Command\JoinFamilyByInvitationHandler;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Entity\InvitationUse;
use App\Family\Domain\Exception\InvitationLimitExceededException;
use App\Family\Domain\Exception\InvitationRevokedException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Family\Infrastructure\Security\InvitationTokenGenerator;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Uid\Uuid;

class JoinFamilyByInvitationHandlerTest extends TestCase
{
    /**
     * @return array{
     *     MockObject&FamilyRepositoryInterface,
     *     MockObject&FamilyMemberRepositoryInterface,
     *     MockObject&InvitationRepositoryInterface,
     *     MockObject&InvitationUseRepositoryInterface,
     *     InvitationTokenGenerator
     * }
     */
    private function createDependencies(): array
    {
        return [
            $this->createMock(FamilyRepositoryInterface::class),
            $this->createMock(FamilyMemberRepositoryInterface::class),
            $this->createMock(InvitationRepositoryInterface::class),
            $this->createMock(InvitationUseRepositoryInterface::class),
            new InvitationTokenGenerator(),
        ];
    }

    public function testJoinSuccess(): void
    {
        [$familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen] = $this->createDependencies();

        $userId = new UserId(Uuid::v7());
        $family = Family::create('My Family', new Timezone('Europe/Warsaw'), new CurrencyCode('PLN'), new CurrencyCode('PLN'));
        $rawToken = 'token123';
        $tokenHash = $tokenGen->hashToken($rawToken);

        $invitation = Invitation::create($family->getId(), new UserId(Uuid::v7()), $tokenHash, maxUses: 2);

        $invRepo->method('findByTokenHash')->with($tokenHash)->willReturn($invitation);
        $familyRepo->method('findById')->with($family->getId())->willReturn($family);
        $memberRepo->method('isMember')->willReturn(false);
        $useRepo->method('insertIfUnderLimit')->willReturn(true);

        $memberRepo->expects($this->once())->method('save')->with($this->isInstanceOf(FamilyMember::class));

        $handler = new JoinFamilyByInvitationHandler($familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen);

        $result = $handler(new JoinFamilyByInvitationCommand($rawToken, $userId));

        $this->assertSame($family, $result['family']);
        $this->assertFalse($result['already_member']);
    }

    public function testJoinWhenAlreadyMemberDoesNotConsumeUse(): void
    {
        [$familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen] = $this->createDependencies();

        $userId = new UserId(Uuid::v7());
        $family = Family::create('My Family', new Timezone('Europe/Warsaw'), new CurrencyCode('PLN'), new CurrencyCode('PLN'));
        $rawToken = 'token123';
        $tokenHash = $tokenGen->hashToken($rawToken);

        $invitation = Invitation::create($family->getId(), new UserId(Uuid::v7()), $tokenHash, maxUses: 1);

        $invRepo->method('findByTokenHash')->with($tokenHash)->willReturn($invitation);
        $familyRepo->method('findById')->with($family->getId())->willReturn($family);
        $memberRepo->method('isMember')->with($family->getId(), $userId)->willReturn(true);

        // No new invitation use or member saved!
        $useRepo->expects($this->never())->method('insertIfUnderLimit');
        $memberRepo->expects($this->never())->method('save');

        $handler = new JoinFamilyByInvitationHandler($familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen);

        $result = $handler(new JoinFamilyByInvitationCommand($rawToken, $userId));

        $this->assertSame($family, $result['family']);
        $this->assertTrue($result['already_member']);
    }

    public function testJoinRevokedThrowsException(): void
    {
        [$familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen] = $this->createDependencies();

        $userId = new UserId(Uuid::v7());
        $family = Family::create('My Family', new Timezone('Europe/Warsaw'), new CurrencyCode('PLN'), new CurrencyCode('PLN'));
        $rawToken = 'token123';
        $tokenHash = $tokenGen->hashToken($rawToken);

        $invitation = Invitation::create($family->getId(), new UserId(Uuid::v7()), $tokenHash);
        $invitation->revoke();

        $invRepo->method('findByTokenHash')->willReturn($invitation);
        $familyRepo->method('findById')->willReturn($family);
        $memberRepo->method('isMember')->willReturn(false);

        $handler = new JoinFamilyByInvitationHandler($familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen);

        $this->expectException(InvitationRevokedException::class);
        $handler(new JoinFamilyByInvitationCommand($rawToken, $userId));
    }

    public function testJoinLimitExceededThrowsException(): void
    {
        [$familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen] = $this->createDependencies();

        $userId = new UserId(Uuid::v7());
        $family = Family::create('My Family', new Timezone('Europe/Warsaw'), new CurrencyCode('PLN'), new CurrencyCode('PLN'));
        $rawToken = 'token123';
        $tokenHash = $tokenGen->hashToken($rawToken);

        $invitation = Invitation::create($family->getId(), new UserId(Uuid::v7()), $tokenHash, maxUses: 1);

        $invRepo->method('findByTokenHash')->willReturn($invitation);
        $familyRepo->method('findById')->willReturn($family);
        $memberRepo->method('isMember')->willReturn(false);
        $useRepo->method('insertIfUnderLimit')->willReturn(false); // Insert failed
        $useRepo->method('countByInvitationId')->willReturn(1); // Count equals maxUses (1)

        $handler = new JoinFamilyByInvitationHandler($familyRepo, $memberRepo, $invRepo, $useRepo, $tokenGen);

        $this->expectException(InvitationLimitExceededException::class);
        $handler(new JoinFamilyByInvitationCommand($rawToken, $userId));
    }
}
