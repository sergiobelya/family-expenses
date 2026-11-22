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

final readonly class DoctrineCatalogRepository implements CatalogRepositoryInterface, FamilyCatalogLockInterface
{
    /** @var EntityRepository<Store> */
    private EntityRepository $stores;

    /** @var EntityRepository<Category> */
    private EntityRepository $categories;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->stores = $entityManager->getRepository(Store::class);
        $this->categories = $entityManager->getRepository(Category::class);
    }

    public function lockFamily(FamilyId $familyId): void
    {
        $found = $this->entityManager->getConnection()->fetchOne(
            'SELECT id FROM families WHERE id = CAST(:id AS UUID) FOR NO KEY UPDATE',
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

    public function removeStore(Store $store): void
    {
        $this->entityManager->remove($store);
    }

    public function removeCategory(Category $category): void
    {
        $this->entityManager->remove($category);
    }

    public function findStore(FamilyId $familyId, StoreId $storeId): ?Store
    {
        return $this->stores->findOneBy(['familyId' => $familyId, 'id' => $storeId]);
    }

    public function findStoreWithAliases(FamilyId $familyId, StoreId $storeId): ?Store
    {
        return $this->stores->findOneBy(['familyId' => $familyId, 'id' => $storeId]);
    }

    public function findCategory(FamilyId $familyId, CategoryId $categoryId): ?Category
    {
        return $this->categories->findOneBy(['familyId' => $familyId, 'id' => $categoryId]);
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
        return $this->entityManager->createQueryBuilder()
            ->select('alias')
            ->from(StoreAlias::class, 'alias')
            ->where('alias.familyId = :familyId')
            ->andWhere('alias.store = :store')
            ->setParameter('familyId', $familyId)
            ->setParameter('store', $storeId)
            ->orderBy('alias.alias', 'ASC')
            ->addOrderBy('alias.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findStoreAlias(FamilyId $familyId, StoreId $storeId, StoreAliasId $aliasId): ?StoreAlias
    {
        return $this->entityManager->createQueryBuilder()
            ->select('alias')
            ->from(StoreAlias::class, 'alias')
            ->where('alias.familyId = :familyId')
            ->andWhere('alias.store = :store')
            ->andWhere('alias.id = :aliasId')
            ->setParameter('familyId', $familyId)
            ->setParameter('store', $storeId)
            ->setParameter('aliasId', $aliasId)
            ->getQuery()
            ->getOneOrNullResult();
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
