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
}
