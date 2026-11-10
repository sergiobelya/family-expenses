<?php

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;

interface CategoryCatalogRepositoryInterface
{
    public function saveCategory(Category $category): void;

    public function removeCategory(Category $category): void;

    public function findCategory(FamilyId $familyId, CategoryId $categoryId): ?Category;

    public function findCategoryByDedupeKey(
        FamilyId $familyId,
        string $dedupeKey,
        ?CategoryId $excludeCategoryId = null
    ): ?Category;

    /**
     * @return Category[]
     */
    public function findCategories(FamilyId $familyId): array;

    public function isCategoryUsedAsStoreDefault(FamilyId $familyId, CategoryId $categoryId): bool;
}
