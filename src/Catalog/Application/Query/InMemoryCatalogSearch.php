<?php

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Core\Domain\ValueObject\FamilyId;
use InvalidArgumentException;

/**
 * @deprecated Use PgTrgmCatalogSearch instead, which uses PostgreSQL's pg_trgm extension for better performance.
 * @see PgTrgmCatalogSearch
 */
final class InMemoryCatalogSearch implements CatalogSearchInterface
{
    public function __construct(private CatalogRepositoryInterface $catalog)
    {
    }

    /**
     * @return Store[]
     */
    public function stores(FamilyId $familyId, string $query): array
    {
        try {
            $queryKey = CatalogName::fromString($query)->getDedupeKey();
        } catch (InvalidArgumentException) {
            return [];
        }

        $allStores = $this->catalog->findStores($familyId);
        $distances = [];
        foreach ($allStores as $store) {
            $distances[(string) $store->getId()] = $this->distance($queryKey, $store->getDedupeKey());
            foreach ($store->getAliases() as $alias) {
                $storeId = (string) $store->getId();
                $distance = $this->distance($queryKey, $alias->getAliasDedupeKey());
                if (!isset($distances[$storeId]) || $distance < $distances[$storeId]) {
                    $distances[$storeId] = $distance;
                }
            }
        }

        $stores = [];
        foreach ($allStores as $store) {
            if (($distances[(string) $store->getId()] ?? PHP_INT_MAX) <= 1) {
                $stores[] = $store;
            }
        }

        usort($stores, function (Store $left, Store $right) use ($distances): int {
            $distance = $distances[(string) $left->getId()] <=> $distances[(string) $right->getId()];

            return $distance !== 0 ? $distance : strcasecmp((string) $left->getName(), (string) $right->getName());
        });

        return $stores;
    }

    /**
     * @return Category[]
     */
    public function categories(FamilyId $familyId, string $query): array
    {
        try {
            $queryKey = CatalogName::fromString($query)->getDedupeKey();
        } catch (InvalidArgumentException) {
            return [];
        }

        $categories = array_filter(
            $this->catalog->findCategories($familyId),
            fn (Category $category): bool => $this->distance($queryKey, $category->getDedupeKey()) <= 1
        );

        usort($categories, fn (Category $left, Category $right): int =>
            $this->distance($queryKey, $left->getDedupeKey()) <=> $this->distance($queryKey, $right->getDedupeKey())
            ?: strcasecmp((string) $left->getName(), (string) $right->getName())
        );

        return $categories;
    }

    private function distance(string $left, string $right): int
    {
        $leftChars = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY);
        $rightChars = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY);
        if ($leftChars === false || $rightChars === false) {
            throw new \LogicException('Catalog dedupe keys must be valid UTF-8.');
        }
        if (abs(count($leftChars) - count($rightChars)) > 1) {
            return 2;
        }

        $previous = range(0, count($rightChars));
        foreach ($leftChars as $i => $leftChar) {
            $current = [$i + 1];
            foreach ($rightChars as $j => $rightChar) {
                $current[] = min(
                    $current[$j] + 1,
                    $previous[$j + 1] + 1,
                    $previous[$j] + ($leftChar === $rightChar ? 0 : 1)
                );
            }
            $previous = $current;
        }

        return $previous[count($rightChars)];
    }
}
