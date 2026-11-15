<?php

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Store;
use App\Core\Domain\ValueObject\FamilyId;

interface CatalogSearchInterface
{
    /**
     * Search for stores by name (fuzzy match).
     *
     * @return Store[]
     */
    public function stores(FamilyId $familyId, string $query): array;

    /**
     * Search for categories by name (fuzzy match).
     *
     * @return Category[]
     */
    public function categories(FamilyId $familyId, string $query): array;
}
