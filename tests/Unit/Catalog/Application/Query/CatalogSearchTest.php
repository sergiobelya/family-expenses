<?php

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\CatalogSearch;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Entity\StoreAlias;
use App\Core\Domain\ValueObject\FamilyId;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CatalogSearchTest extends TestCase
{
    public function testSearchFindsRussianTransliterationVariantBySingleEditSuggestion(): void
    {
        $familyId = FamilyId::generate();
        $store = Store::create($familyId, 'Biedronka');
        $repository = $this->createMock(CatalogRepositoryInterface::class);
        $repository->method('findStores')->with($familyId)->willReturn([$store]);
        $repository->method('findStoreAliasesInFamily')->with($familyId)->willReturn([]);

        self::assertSame([$store], (new CatalogSearch($repository))->stores($familyId, 'бедронка'));
    }

    public function testSearchMatchesFamilyScopedStoreAlias(): void
    {
        $familyId = FamilyId::generate();
        $store = Store::create($familyId, 'Local Market');
        $alias = StoreAlias::create($familyId, $store->getId(), 'Biedronka');
        $repository = $this->createMock(CatalogRepositoryInterface::class);
        $repository->method('findStores')->with($familyId)->willReturn([$store]);
        $repository->method('findStoreAliasesInFamily')->with($familyId)->willReturn([$alias]);

        self::assertSame([$store], (new CatalogSearch($repository))->stores($familyId, 'Бєдронка'));
    }

    public function testInvalidSearchInputReturnsNoCandidates(): void
    {
        $familyId = FamilyId::generate();
        $repository = $this->createMock(CatalogRepositoryInterface::class);
        $repository->expects(self::never())->method('findStores');

        self::assertSame([], (new CatalogSearch($repository))->stores($familyId, '🍎'));
    }
}
