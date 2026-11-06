<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Repository;

use App\Core\Infrastructure\Persistence\Doctrine\Entity\OutboxMessage;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class OutboxRepository
{
    /** @var EntityRepository<OutboxMessage> */
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(OutboxMessage::class);
    }

    public function append(OutboxMessage $message): void
    {
        $this->entityManager->persist($message);
    }

    public function save(OutboxMessage $message): void
    {
        $this->entityManager->persist($message);
        $this->entityManager->flush();
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * @return OutboxMessage[]
     */
    public function findPending(int $limit = 100, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->repository->createQueryBuilder('message')
            ->where('message.status = :pending')
            ->andWhere('message.nextAttemptAt <= :now')
            ->setParameter('pending', OutboxMessage::STATUS_PENDING)
            ->setParameter('now', $now)
            ->orderBy('message.nextAttemptAt', 'ASC')
            ->addOrderBy('message.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return OutboxMessage[]
     */
    public function findFailed(int $limit = 100): array
    {
        return $this->repository->findBy(
            ['status' => OutboxMessage::STATUS_FAILED],
            ['createdAt' => 'ASC'],
            $limit
        );
    }

    public function countPending(): int
    {
        return (int) $this->repository->count(['status' => OutboxMessage::STATUS_PENDING]);
    }

    public function countFailed(): int
    {
        return (int) $this->repository->count(['status' => OutboxMessage::STATUS_FAILED]);
    }
}
