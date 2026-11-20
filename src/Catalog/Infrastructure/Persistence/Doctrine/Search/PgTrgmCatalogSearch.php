<?php

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Search;

use App\Catalog\Application\Query\CatalogSearchInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\FamilyId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * Catalog search implementation using PostgreSQL's pg_trgm extension for fuzzy matching.
 */
final class PgTrgmCatalogSearch implements CatalogSearchInterface
{
    private const float DISTANCE_THRESHOLD = 0.6;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return Store[]
     */
    public function stores(FamilyId $familyId, string $query): array
    {
        $normalized = $this->normalizeQuery($query);
        if ($normalized === null) {
            return [];
        }

        // Find stores whose dedupe_key or any alias's alias_dedupe_key is similar to the query.
        // Uses pg_trgm <-> operator for distance-based ranking.
        // Takes the minimum distance between store name and all aliases for each store.
        $sql = <<<'SQL'
            SELECT store.id, store.family_id, store.name, store.dedupe_key,
                   store.default_category_id, store.created_at, store.updated_at,
                   LEAST(
                       store.dedupe_key <-> :query,
                       (SELECT MIN(alias.alias_dedupe_key <-> :query2)
                        FROM store_aliases alias
                        WHERE alias.store_id = store.id)
                   ) AS distance
            FROM stores store
            WHERE store.family_id = CAST(:familyId AS UUID)
              AND (
                   store.dedupe_key <-> :query3 <= :threshold
                   OR EXISTS (
                       SELECT 1 FROM store_aliases alias
                       WHERE alias.store_id = store.id
                         AND alias.alias_dedupe_key <-> :query4 <= :threshold
                   )
               )
            ORDER BY distance ASC, store.name ASC
        SQL;

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Store::class, 's');
        $rsm->addFieldResult('s', 'id', 'id');
        $rsm->addFieldResult('s', 'family_id', 'familyId');
        $rsm->addFieldResult('s', 'name', 'name');
        $rsm->addFieldResult('s', 'dedupe_key', 'dedupeKey');
        $rsm->addFieldResult('s', 'default_category_id', 'defaultCategoryId');
        $rsm->addFieldResult('s', 'created_at', 'createdAt');
        $rsm->addFieldResult('s', 'updated_at', 'updatedAt');

        $nativeQuery = $this->entityManager->createNativeQuery($sql, $rsm);
        $nativeQuery->setParameter('familyId', (string) $familyId);
        $nativeQuery->setParameter('query', $normalized);
        $nativeQuery->setParameter('query2', $normalized);
        $nativeQuery->setParameter('query3', $normalized);
        $nativeQuery->setParameter('query4', $normalized);
        $nativeQuery->setParameter('threshold', self::DISTANCE_THRESHOLD);

        /** @var Store[] $stores */
        $stores = $nativeQuery->getResult();

        return $stores;
    }

    /**
     * @return Category[]
     */
    public function categories(FamilyId $familyId, string $query): array
    {
        $normalized = $this->normalizeQuery($query);
        if ($normalized === null) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT category.id, category.family_id, category.name, category.dedupe_key,
                   category.created_at, category.updated_at,
                   category.dedupe_key <-> :query AS distance
            FROM categories category
            WHERE category.family_id = CAST(:familyId AS UUID)
              AND category.dedupe_key <-> :query2 <= :threshold
            ORDER BY distance ASC, category.name ASC
        SQL;

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Category::class, 'c');
        $rsm->addFieldResult('c', 'id', 'id');
        $rsm->addFieldResult('c', 'family_id', 'familyId');
        $rsm->addFieldResult('c', 'name', 'name');
        $rsm->addFieldResult('c', 'dedupe_key', 'dedupeKey');
        $rsm->addFieldResult('c', 'created_at', 'createdAt');
        $rsm->addFieldResult('c', 'updated_at', 'updatedAt');

        $nativeQuery = $this->entityManager->createNativeQuery($sql, $rsm);
        $nativeQuery->setParameter('familyId', (string) $familyId);
        $nativeQuery->setParameter('query', $normalized);
        $nativeQuery->setParameter('query2', $normalized);
        $nativeQuery->setParameter('threshold', self::DISTANCE_THRESHOLD);

        /** @var Category[] $categories */
        $categories = $nativeQuery->getResult();

        return $categories;
    }

    /**
     * Normalize the search query using the same dedupe logic as CatalogName.
     * Returns null if the query is invalid.
     */
    private function normalizeQuery(string $query): ?string
    {
        try {
            return CatalogName::fromString($query)->getDedupeKey();
        } catch (InvalidValueException) {
            return null;
        }
    }
}
