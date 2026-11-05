<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Repository;

use App\Core\Infrastructure\Persistence\Doctrine\Entity\TelegramUpdateInbox;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class TelegramUpdateInboxRepository
{
    /** @var EntityRepository<TelegramUpdateInbox> */
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(TelegramUpdateInbox::class);
    }

    public function recordIfUnprocessed(int $updateId): ?TelegramUpdateInbox
    {
        $existing = $this->repository->findOneBy(['updateId' => $updateId]);
        if ($existing !== null) {
            return $existing->getProcessedAt() === null ? $existing : null;
        }

        $inbox = new TelegramUpdateInbox($updateId);
        $this->entityManager->persist($inbox);
        $this->entityManager->flush();

        return $inbox;
    }

    public function markProcessed(TelegramUpdateInbox $inbox): void
    {
        $inbox->markAsProcessed();
        $this->entityManager->flush();
    }

    public function getNextUpdateOffset(): ?int
    {
        $lastProcessed = $this->entityManager->createQueryBuilder()
            ->select('inbox.updateId')
            ->from(TelegramUpdateInbox::class, 'inbox')
            ->where('inbox.processedAt IS NOT NULL')
            ->orderBy('inbox.updateId', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $lastProcessed === null ? null : (int) $lastProcessed['updateId'] + 1;
    }
}
