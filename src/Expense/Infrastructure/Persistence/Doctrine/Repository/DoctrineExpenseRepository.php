<?php

namespace App\Expense\Infrastructure\Persistence\Doctrine\Repository;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Core\Domain\ValueObject\ExpenseId;
use App\Core\Domain\ValueObject\FamilyId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(Expense $expense): void
    {
        $this->entityManager->persist($expense);
    }

    public function findById(ExpenseId $id): ?Expense
    {
        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Expense::class, 'e')
            ->where('e.id = :id')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Expense[]
     */
    public function findByFamilyId(FamilyId $familyId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Expense::class, 'e')
            ->where('e.familyId = :familyId')
            ->andWhere('e.deletedAt IS NULL')
            ->setParameter('familyId', $familyId)
            ->orderBy('e.purchaseDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
