<?php

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Entity\StoreAlias;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreAliasId;
use App\Core\Domain\ValueObject\StoreId;

interface StoreCatalogRepositoryInterface
{
    public function saveStore(Store $store): void;

    public function saveStoreAlias(StoreAlias $alias): void;

    public function removeStore(Store $store): void;

    public function removeStoreAlias(StoreAlias $alias): void;

    public function findStore(FamilyId $familyId, StoreId $storeId): ?Store;

    public function findStoreAlias(FamilyId $familyId, StoreId $storeId, StoreAliasId $aliasId): ?StoreAlias;

    public function findStoreByDedupeKey(FamilyId $familyId, string $dedupeKey, ?StoreId $excludeStoreId = null): ?Store;

    public function findStoreAliasByDedupeKey(
        FamilyId $familyId,
        string $dedupeKey,
        ?StoreAliasId $excludeAliasId = null
    ): ?StoreAlias;

    /**
     * @return Store[]
     */
    public function findStores(FamilyId $familyId): array;

    /**
     * @return StoreAlias[]
     */
    public function findStoreAliases(FamilyId $familyId, StoreId $storeId): array;

    /**
     * @return StoreAlias[]
     */
    public function findStoreAliasesInFamily(FamilyId $familyId): array;
}
