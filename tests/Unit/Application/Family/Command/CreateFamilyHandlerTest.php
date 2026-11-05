<?php

namespace App\Tests\Unit\Application\Family\Command;

use App\Family\Application\Command\CreateFamilyCommand;
use App\Family\Application\Command\CreateFamilyHandler;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

class CreateFamilyHandlerTest extends TestCase
{
    public function testCreateFamilySuccess(): void
    {
        $user = new User(123456);

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())->method('findById')->with($user->getId())->willReturn($user);
        $userRepo->expects($this->once())->method('save')->with($user);

        $familyRepo = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepo->expects($this->once())->method('save')->with($this->isInstanceOf(Family::class));

        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->expects($this->once())->method('save')->with($this->isInstanceOf(FamilyMember::class));

        $handler = new CreateFamilyHandler($userRepo, $familyRepo, $memberRepo);

        $command = new CreateFamilyCommand(
            userId: $user->getId(),
            name: 'Our Family',
            timezone: 'Europe/Kyiv',
            defaultExpenseCurrency: 'UAH',
            reportingCurrency: 'USD'
        );

        $family = $handler($command);

        $this->assertSame('Our Family', $family->getName());
        $this->assertSame('Europe/Kyiv', (string) $family->getTimezone());
        $this->assertSame('UAH', (string) $family->getDefaultExpenseCurrency());
        $this->assertSame('USD', (string) $family->getReportingCurrency());
        $this->assertEquals($family->getId(), $user->getActiveFamilyId());
    }
}
