<?php

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Entity\StoreAlias;
use Symfony\Component\Uid\Uuid;

interface CatalogRepositoryInterface
{
    public function lockFamily(Uuid $familyId): void;

    public function saveStore(Store $store): void;

    public function saveCategory(Category $category): void;

    public function saveStoreAlias(StoreAlias $alias): void;

    public function removeStore(Store $store): void;

    public function removeCategory(Category $category): void;

    public function removeStoreAlias(StoreAlias $alias): void;

    public function findStore(Uuid $familyId, Uuid $storeId): ?Store;

    public function findCategory(Uuid $familyId, Uuid $categoryId): ?Category;

    public function findStoreAlias(Uuid $familyId, Uuid $storeId, Uuid $aliasId): ?StoreAlias;

    public function findStoreByDedupeKey(Uuid $familyId, string $dedupeKey, ?Uuid $excludeStoreId = null): ?Store;

    public function findCategoryByDedupeKey(
        Uuid $familyId,
        string $dedupeKey,
        ?Uuid $excludeCategoryId = null
    ): ?Category;

    public function findStoreAliasByDedupeKey(
        Uuid $familyId,
        string $dedupeKey,
        ?Uuid $excludeAliasId = null
    ): ?StoreAlias;

    /**
     * @return Store[]
     */
    public function findStores(Uuid $familyId): array;

    /**
     * @return Category[]
     */
    public function findCategories(Uuid $familyId): array;

    /**
     * @return StoreAlias[]
     */
    public function findStoreAliases(Uuid $familyId, Uuid $storeId): array;

    /**
     * @return StoreAlias[]
     */
    public function findStoreAliasesInFamily(Uuid $familyId): array;

    public function isCategoryUsedAsStoreDefault(Uuid $familyId, Uuid $categoryId): bool;

}
