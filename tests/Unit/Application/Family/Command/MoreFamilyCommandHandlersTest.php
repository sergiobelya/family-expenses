<?php

namespace App\Tests\Unit\Application\Family\Command;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Family\Application\Command\CreateInvitationCommand;
use App\Family\Application\Command\CreateInvitationHandler;
use App\Family\Application\Command\RevokeInvitationCommand;
use App\Family\Application\Command\RevokeInvitationHandler;
use App\Family\Application\Command\UpdateFamilySettingsCommand;
use App\Family\Application\Command\UpdateFamilySettingsHandler;
use App\Core\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Infrastructure\Security\InvitationTokenGenerator;
use PHPUnit\Framework\TestCase;

class MoreFamilyCommandHandlersTest extends TestCase
{
    public function testCreateInvitationSuccess(): void
    {
        $familyId = FamilyId::generate();
        $userId = UserId::generate();

        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->expects($this->once())
            ->method('isMember')
            ->with($familyId, $userId)
            ->willReturn(true);

        $invitationRepo = $this->createMock(InvitationRepositoryInterface::class);
        $invitationRepo->expects($this->once())->method('save')->with($this->isInstanceOf(Invitation::class));

        $tokenGenerator = new InvitationTokenGenerator();

        $handler = new CreateInvitationHandler($memberRepo, $invitationRepo, $tokenGenerator);
        $result = $handler(new CreateInvitationCommand($familyId, $userId, maxUses: 3));

        $this->assertNotEmpty($result['raw_token']);
        $this->assertInstanceOf(Invitation::class, $result['invitation']);
        $this->assertSame(3, $result['invitation']->getMaxUses());
    }

    public function testCreateInvitationFailsIfNotMember(): void
    {
        $familyId = FamilyId::generate();
        $userId = UserId::generate();

        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->method('isMember')->willReturn(false);

        $invitationRepo = $this->createMock(InvitationRepositoryInterface::class);
        $tokenGenerator = new InvitationTokenGenerator();

        $handler = new CreateInvitationHandler($memberRepo, $invitationRepo, $tokenGenerator);

        $this->expectException(NotFamilyMemberException::class);
        $handler(new CreateInvitationCommand($familyId, $userId));
    }

    public function testRevokeInvitationSuccess(): void
    {
        $familyId = FamilyId::generate();
        $userId = UserId::generate();
        $invitation = Invitation::create($familyId, $userId, 'hash123', null, 1);

        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->method('isMember')->willReturn(true);

        $invitationRepo = $this->createMock(InvitationRepositoryInterface::class);
        $invitationRepo->method('findById')->with($familyId, $invitation->getId())->willReturn($invitation);
        $invitationRepo->expects($this->once())->method('save')->with($invitation);

        $handler = new RevokeInvitationHandler($memberRepo, $invitationRepo);
        $handler(new RevokeInvitationCommand($familyId, $invitation->getId(), $userId));

        $this->assertTrue($invitation->isRevoked());
    }

    public function testUpdateFamilySettingsSuccess(): void
    {
        $family = Family::create('Old Name', new Timezone('UTC'), new CurrencyCode('USD'), new CurrencyCode('USD'));
        $userId = UserId::generate();

        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->method('isMember')->with($family->getId(), $userId)->willReturn(true);

        $familyRepo = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepo->method('findById')->with($family->getId())->willReturn($family);
        $familyRepo->expects($this->once())->method('save')->with($family);

        $handler = new UpdateFamilySettingsHandler($familyRepo, $memberRepo);
        $handler(new UpdateFamilySettingsCommand(
            familyId: $family->getId(),
            updatedByUserId: $userId,
            name: 'New Name',
            timezone: 'Europe/Warsaw',
            defaultExpenseCurrency: 'PLN',
            reportingCurrency: 'EUR',
            defaultExchangeRateProvider: 'nbp',
            countryCode: 'PL'
        ));

        $this->assertSame('New Name', (string) $family->getName());
        $this->assertSame('PL', (string) $family->getCountryCode());
        $this->assertSame('Europe/Warsaw', (string) $family->getTimezone());
        $this->assertSame('PLN', (string) $family->getDefaultExpenseCurrency());
        $this->assertSame('EUR', (string) $family->getReportingCurrency());
        $this->assertSame('nbp', $family->getDefaultExchangeRateProvider());
    }
}
