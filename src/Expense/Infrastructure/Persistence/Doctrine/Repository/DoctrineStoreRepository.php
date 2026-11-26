<?php

namespace App\Expense\Infrastructure\Persistence\Doctrine\Repository;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\Repository\StoreRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineStoreRepository implements StoreRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function findById(StoreId $id, FamilyId $familyId): ?ExpenseStore
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(ExpenseStore::class, 's')
            ->where('s.id = :id')
            ->andWhere('s.familyId = :familyId')
            ->setParameter('id', $id)
            ->setParameter('familyId', $familyId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<StoreId> */
    public function findIdsByLastPurchase(FamilyId $familyId): array
    {
        $rows = $this->entityManager->getConnection()->executeQuery(
            <<<'SQL'
                SELECT s.id
                FROM stores AS s
                LEFT JOIN expenses AS e
                    ON e.store_id = s.id
                    AND e.family_id = s.family_id
                    AND e.deleted_at IS NULL
                WHERE s.family_id = CAST(:familyId AS UUID)
                GROUP BY s.id, s.name, s.family_id
                ORDER BY MAX(e.purchase_date) DESC NULLS LAST, LOWER(s.name) ASC, s.name ASC, s.id ASC
                SQL,
            ['familyId' => (string) $familyId]
        )->fetchAllAssociative();

        return array_map(
            static fn (array $row): StoreId => StoreId::fromString((string) $row['id']),
            $rows
        );
    }
}
