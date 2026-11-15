<?php

namespace App\Tests\Unit\Application\Family\Command;

use App\Family\Application\Command\CreateFamilyCommand;
use App\Family\Application\Command\CreateFamilyHandler;
use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Core\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class CreateFamilyHandlerTest extends TestCase
{
    public function testCreateFamilySuccess(): void
    {
        $userId = new UserId(Uuid::v7());

        $familyRepo = $this->createMock(FamilyRepositoryInterface::class);
        $familyRepo->expects($this->once())->method('save')->with($this->isInstanceOf(Family::class));

        $memberRepo = $this->createMock(FamilyMemberRepositoryInterface::class);
        $memberRepo->expects($this->once())->method('save')->with($this->isInstanceOf(FamilyMember::class));

        $handler = new CreateFamilyHandler($familyRepo, $memberRepo);

        $command = new CreateFamilyCommand(
            userId: $userId,
            name: 'Our Family',
            timezone: 'Europe/Kyiv',
            defaultExpenseCurrency: 'UAH',
            reportingCurrency: 'USD'
        );

        $family = $handler($command);

        $this->assertSame('Our Family', (string) $family->getName());
        $this->assertSame('Europe/Kyiv', (string) $family->getTimezone());
        $this->assertSame('UAH', (string) $family->getDefaultExpenseCurrency());
        $this->assertSame('USD', (string) $family->getReportingCurrency());
    }
}
