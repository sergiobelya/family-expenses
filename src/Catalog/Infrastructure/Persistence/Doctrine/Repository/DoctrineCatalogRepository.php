<?php

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Application\FamilyCatalogLockInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Entity\StoreAlias;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use App\Family\Domain\Exception\FamilyNotFoundException;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreAliasId;
use App\Core\Domain\ValueObject\StoreId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineCatalogRepository implements CatalogRepositoryInterface, FamilyCatalogLockInterface
{
    /** @var EntityRepository<Store> */
    private EntityRepository $stores;

    /** @var EntityRepository<Category> */
    private EntityRepository $categories;

    /** @var EntityRepository<StoreAlias> */
    private EntityRepository $aliases;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->stores = $entityManager->getRepository(Store::class);
        $this->categories = $entityManager->getRepository(Category::class);
        $this->aliases = $entityManager->getRepository(StoreAlias::class);
    }

    public function lockFamily(FamilyId $familyId): void
    {
        $found = $this->entityManager->getConnection()->fetchOne(
            'SELECT id FROM families WHERE id = CAST(:id AS UUID) FOR UPDATE',
            ['id' => (string) $familyId]
        );
        if ($found === false) {
            throw FamilyNotFoundException::withId((string) $familyId);
        }
    }

    public function saveStore(Store $store): void
    {
        $this->entityManager->persist($store);
    }

    public function saveCategory(Category $category): void
    {
        $this->entityManager->persist($category);
    }

    public function saveStoreAlias(StoreAlias $alias): void
    {
        $this->entityManager->persist($alias);
    }

    public function removeStore(Store $store): void
    {
        $this->entityManager->remove($store);
    }

    public function removeCategory(Category $category): void
    {
        $this->entityManager->remove($category);
    }

    public function removeStoreAlias(StoreAlias $alias): void
    {
        $this->entityManager->remove($alias);
    }

    public function findStore(FamilyId $familyId, StoreId $storeId): ?Store
    {
        return $this->stores->findOneBy(['familyId' => $familyId, 'id' => $storeId]);
    }

    public function findCategory(FamilyId $familyId, CategoryId $categoryId): ?Category
    {
        return $this->categories->findOneBy(['familyId' => $familyId, 'id' => $categoryId]);
    }

    public function findStoreAlias(FamilyId $familyId, StoreId $storeId, StoreAliasId $aliasId): ?StoreAlias
    {
        return $this->aliases->findOneBy([
            'familyId' => $familyId,
            'storeId' => $storeId,
            'id' => $aliasId,
        ]);
    }

    public function findStoreByDedupeKey(FamilyId $familyId, string $dedupeKey, ?StoreId $excludeStoreId = null): ?Store
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('store')
            ->from(Store::class, 'store')
            ->where('store.familyId = :familyId')
            ->andWhere('store.dedupeKey = :dedupeKey')
            ->setParameter('familyId', $familyId)
            ->setParameter('dedupeKey', $dedupeKey)
            ->setMaxResults(1);
        if ($excludeStoreId !== null) {
            $queryBuilder->andWhere('store.id != :excludeId')->setParameter('excludeId', $excludeStoreId);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function findCategoryByDedupeKey(
        FamilyId $familyId,
        string $dedupeKey,
        ?CategoryId $excludeCategoryId = null
    ): ?Category {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('category')
            ->from(Category::class, 'category')
            ->where('category.familyId = :familyId')
            ->andWhere('category.dedupeKey = :dedupeKey')
            ->setParameter('familyId', $familyId)
            ->setParameter('dedupeKey', $dedupeKey)
            ->setMaxResults(1);
        if ($excludeCategoryId !== null) {
            $queryBuilder->andWhere('category.id != :excludeId')->setParameter('excludeId', $excludeCategoryId);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function findStoreAliasByDedupeKey(
        FamilyId $familyId,
        string $dedupeKey,
        ?StoreAliasId $excludeAliasId = null
    ): ?StoreAlias {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('alias')
            ->from(StoreAlias::class, 'alias')
            ->where('alias.familyId = :familyId')
            ->andWhere('alias.aliasDedupeKey = :dedupeKey')
            ->setParameter('familyId', $familyId)
            ->setParameter('dedupeKey', $dedupeKey)
            ->setMaxResults(1);

        if ($excludeAliasId !== null) {
            $queryBuilder->andWhere('alias.id != :excludeAliasId')
                ->setParameter('excludeAliasId', $excludeAliasId);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function findStores(FamilyId $familyId): array
    {
        return $this->stores->findBy(['familyId' => $familyId], ['name' => 'ASC', 'id' => 'ASC']);
    }

    public function findCategories(FamilyId $familyId): array
    {
        return $this->categories->findBy(['familyId' => $familyId], ['name' => 'ASC', 'id' => 'ASC']);
    }

    public function findStoreAliases(FamilyId $familyId, StoreId $storeId): array
    {
        return $this->aliases->findBy(
            ['familyId' => $familyId, 'storeId' => $storeId],
            ['alias' => 'ASC', 'id' => 'ASC']
        );
    }

    public function findStoreAliasesInFamily(FamilyId $familyId): array
    {
        return $this->aliases->findBy(['familyId' => $familyId], ['alias' => 'ASC', 'id' => 'ASC']);
    }

    public function isCategoryUsedAsStoreDefault(FamilyId $familyId, CategoryId $categoryId): bool
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(store.id)')
            ->from(Store::class, 'store')
            ->where('store.familyId = :familyId')
            ->andWhere('store.defaultCategoryId = :categoryId')
            ->setParameter('familyId', $familyId)
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
