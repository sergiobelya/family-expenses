<?php

namespace App\Tests\Unit\Presentation\Telegram\Context;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Enum\FamilyMemberStatus;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Presentation\Telegram\Context\ResolveActiveFamilyForUser;
use PHPUnit\Framework\TestCase;

class ResolveActiveFamilyForUserTest extends TestCase
{
    public function testReturnsNullWithoutLookingUpFamilyWhenUserHasNoActiveFamily(): void
    {
        $user = new User(123456);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::never())->method('save');

        $familyRepository = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepository->expects(self::never())->method('findById');

        $familyMemberRepository = $this->createMock(FamilyMemberRepositoryInterface::class);
        $familyMemberRepository->expects(self::never())->method('findByFamilyAndUser');

        $resolve = new ResolveActiveFamilyForUser($userRepository, $familyRepository, $familyMemberRepository);

        self::assertNull($resolve($user));
    }

    public function testReturnsFamilyWhenUserHasActiveMembership(): void
    {
        $user = new User(123456);
        $family = $this->createFamily();
        $user->switchActiveFamily($family->getId());

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::never())->method('save');

        $familyRepository = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepository->expects(self::once())
            ->method('findById')
            ->with($family->getId())
            ->willReturn($family);

        $familyMemberRepository = $this->createMock(FamilyMemberRepositoryInterface::class);
        $familyMemberRepository->expects(self::once())
            ->method('findByFamilyAndUser')
            ->with($family->getId(), $user->getId())
            ->willReturn(FamilyMember::join($family->getId(), $user->getId()));

        $resolve = new ResolveActiveFamilyForUser($userRepository, $familyRepository, $familyMemberRepository);

        self::assertSame($family, $resolve($user));
        $activeFamilyId = $user->getActiveFamilyId();
        self::assertNotNull($activeFamilyId);
        self::assertTrue($family->getId()->equals($activeFamilyId));
    }

    public function testClearsAndSavesActiveFamilyWhenFamilyDoesNotExist(): void
    {
        $user = new User(123456);
        $familyId = $this->createFamily()->getId();
        $user->switchActiveFamily($familyId);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('save')->with($user);

        $familyRepository = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepository->expects(self::once())->method('findById')->with($familyId)->willReturn(null);

        $familyMemberRepository = $this->createMock(FamilyMemberRepositoryInterface::class);
        $familyMemberRepository->expects(self::never())->method('findByFamilyAndUser');

        $resolve = new ResolveActiveFamilyForUser($userRepository, $familyRepository, $familyMemberRepository);

        self::assertNull($resolve($user));
        self::assertNull($user->getActiveFamilyId());
    }

    public function testClearsAndSavesActiveFamilyWhenUserIsNotAMember(): void
    {
        $user = new User(123456);
        $family = $this->createFamily();
        $user->switchActiveFamily($family->getId());

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('save')->with($user);

        $familyRepository = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepository->expects(self::once())->method('findById')->with($family->getId())->willReturn($family);

        $familyMemberRepository = $this->createMock(FamilyMemberRepositoryInterface::class);
        $familyMemberRepository->expects(self::once())
            ->method('findByFamilyAndUser')
            ->with($family->getId(), $user->getId())
            ->willReturn(null);

        $resolve = new ResolveActiveFamilyForUser($userRepository, $familyRepository, $familyMemberRepository);

        self::assertNull($resolve($user));
        self::assertNull($user->getActiveFamilyId());
    }

    public function testClearsAndSavesActiveFamilyWhenMembershipIsInactive(): void
    {
        $user = new User(123456);
        $family = $this->createFamily();
        $user->switchActiveFamily($family->getId());

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('save')->with($user);

        $familyRepository = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepository->expects(self::once())->method('findById')->with($family->getId())->willReturn($family);

        $familyMemberRepository = $this->createMock(FamilyMemberRepositoryInterface::class);
        $familyMemberRepository->expects(self::once())
            ->method('findByFamilyAndUser')
            ->with($family->getId(), $user->getId())
            ->willReturn(FamilyMember::join($family->getId(), $user->getId(), status: FamilyMemberStatus::INACTIVE));

        $resolve = new ResolveActiveFamilyForUser($userRepository, $familyRepository, $familyMemberRepository);

        self::assertNull($resolve($user));
        self::assertNull($user->getActiveFamilyId());
    }

    private function createFamily(): Family
    {
        return Family::create(
            'Smiths',
            new Timezone('UTC'),
            new CurrencyCode('USD'),
            new CurrencyCode('USD')
        );
    }
}
