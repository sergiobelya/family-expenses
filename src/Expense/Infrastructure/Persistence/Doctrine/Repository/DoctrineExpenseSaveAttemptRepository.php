<?php

namespace App\Expense\Infrastructure\Persistence\Doctrine\Repository;

use App\Expense\Domain\ExpenseSaveAttemptStatus;
use App\Expense\Domain\Entity\ExpenseSaveAttempt;
use App\Expense\Domain\Repository\ExpenseSaveAttemptRepositoryInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Domain\ValueObject\ExpenseSaveAttemptId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineExpenseSaveAttemptRepository implements ExpenseSaveAttemptRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(ExpenseSaveAttempt $attempt): void
    {
        $this->entityManager->persist($attempt);
    }

    public function findById(ExpenseSaveAttemptId $id, FamilyId $familyId): ?ExpenseSaveAttempt
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(ExpenseSaveAttempt::class, 'a')
            ->where('a.id = :id')
            ->andWhere('a.familyId = :familyId')
            ->setParameter('id', $id)
            ->setParameter('familyId', $familyId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByIdempotencyKey(string $idempotencyKey, FamilyId $familyId): ?ExpenseSaveAttempt
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(ExpenseSaveAttempt::class, 'a')
            ->where('a.idempotencyKey = :key')
            ->andWhere('a.familyId = :familyId')
            ->setParameter('key', $idempotencyKey)
            ->setParameter('familyId', $familyId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastPendingByUserAndFamily(UserId $userId, FamilyId $familyId): ?ExpenseSaveAttempt
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(ExpenseSaveAttempt::class, 'a')
            ->where('a.userId = :userId')
            ->andWhere('a.familyId = :familyId')
            ->andWhere('a.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('familyId', $familyId)
            ->setParameter('status', ExpenseSaveAttemptStatus::PENDING)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
