<?php

namespace App\Expense\Infrastructure\Persistence\Doctrine\Repository;

use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Expense\Domain\Entity\ExpenseCategory;
use App\Expense\Domain\Repository\CategoryRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function findById(CategoryId $id, FamilyId $familyId): ?ExpenseCategory
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(ExpenseCategory::class, 'c')
            ->where('c.id = :id')
            ->andWhere('c.familyId = :familyId')
            ->setParameter('id', $id)
            ->setParameter('familyId', $familyId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
