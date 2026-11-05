<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Repository;

use App\Core\Infrastructure\Persistence\Doctrine\Entity\OutboxMessage;
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
    public function findPending(int $limit = 100): array
    {
        return $this->repository->findBy(['status' => OutboxMessage::STATUS_PENDING], ['createdAt' => 'ASC'], $limit);
    }

    public function countPending(): int
    {
        return (int) $this->repository->count(['status' => OutboxMessage::STATUS_PENDING]);
    }
}
