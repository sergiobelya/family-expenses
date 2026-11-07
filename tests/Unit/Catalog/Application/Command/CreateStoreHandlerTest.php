<?php

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Application\Command\CreateStoreCommand;
use App\Catalog\Application\Command\CreateStoreHandler;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Exception\CatalogNameConflictException;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreateStoreHandlerTest extends TestCase
{
    public function testCreatesStoreAfterFamilyLockAndMembershipValidation(): void
    {
        $familyId = Uuid::v7();
        $userId = Uuid::v7();
        $catalog = $this->createMock(CatalogRepositoryInterface::class);
        $catalog->expects(self::once())->method('lockFamily')->with($familyId);
        $catalog->method('findStoreByDedupeKey')->with($familyId, 'biedronka')->willReturn(null);
        $catalog->method('findStoreAliasByDedupeKey')->with($familyId, 'biedronka')->willReturn(null);
        $catalog->expects(self::once())->method('saveStore')->with(self::callback(
            static fn (Store $store): bool => $store->getFamilyId()->equals($familyId)
                && $store->getName() === 'Biedronka'
                && $store->getDedupeKey() === 'biedronka'
        ));

        $members = $this->createMock(FamilyMemberRepositoryInterface::class);
        $members->expects(self::exactly(2))->method('isMember')->with($familyId, $userId)->willReturn(true);
        $handler = new CreateStoreHandler($catalog, new CatalogAccess($members, $catalog));

        $store = $handler(new CreateStoreCommand($familyId, $userId, ' Biedronka '));

        self::assertSame('Biedronka', $store->getName());
    }

    public function testRejectsStoreNameThatConflictsWithAlias(): void
    {
        $familyId = Uuid::v7();
        $userId = Uuid::v7();
        $existingStore = Store::create($familyId, 'Market');
        $alias = \App\Catalog\Domain\Entity\StoreAlias::create(
            $familyId,
            $existingStore->getId(),
            'Biedronka'
        );
        $catalog = $this->createMock(CatalogRepositoryInterface::class);
        $catalog->method('findStoreByDedupeKey')->willReturn(null);
        $catalog->method('findStoreAliasByDedupeKey')->willReturn($alias);
        $members = $this->createMock(FamilyMemberRepositoryInterface::class);
        $members->method('isMember')->willReturn(true);
        $handler = new CreateStoreHandler($catalog, new CatalogAccess($members, $catalog));

        $this->expectException(CatalogNameConflictException::class);
        $handler(new CreateStoreCommand($familyId, $userId, 'Biedronka'));
    }
}
