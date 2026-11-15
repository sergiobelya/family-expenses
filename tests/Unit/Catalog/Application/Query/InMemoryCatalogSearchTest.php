<?php

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\InMemoryCatalogSearch;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Entity\StoreAlias;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class InMemoryCatalogSearchTest extends TestCase
{
    public function testSearchFindsRussianTransliterationVariantBySingleEditSuggestion(): void
    {
        $familyId = FamilyId::generate();
        $userId = UserId::generate();
        $store = Store::create($familyId, 'Biedronka', $userId);
        $repository = $this->createMock(CatalogRepositoryInterface::class);
        $repository->method('findStores')->with($familyId)->willReturn([$store]);
        $repository->method('findStoreAliasesInFamily')->with($familyId)->willReturn([]);

        self::assertSame([$store], (new InMemoryCatalogSearch($repository))->stores($familyId, 'бедронка'));
    }

    public function testSearchMatchesFamilyScopedStoreAlias(): void
    {
        $familyId = FamilyId::generate();
        $userId = UserId::generate();
        $store = Store::create($familyId, 'Local Market', $userId);
        $alias = StoreAlias::create($familyId, $store->getId(), 'Biedronka', $userId);
        $repository = $this->createMock(CatalogRepositoryInterface::class);
        $repository->method('findStores')->with($familyId)->willReturn([$store]);
        $repository->method('findStoreAliasesInFamily')->with($familyId)->willReturn([$alias]);

        self::assertSame([$store], (new InMemoryCatalogSearch($repository))->stores($familyId, 'Бєдронка'));
    }

    public function testInvalidSearchInputReturnsNoCandidates(): void
    {
        $familyId = FamilyId::generate();
        $repository = $this->createMock(CatalogRepositoryInterface::class);
        $repository->expects(self::never())->method('findStores');

        self::assertSame([], (new InMemoryCatalogSearch($repository))->stores($familyId, '🍎'));
    }
}
