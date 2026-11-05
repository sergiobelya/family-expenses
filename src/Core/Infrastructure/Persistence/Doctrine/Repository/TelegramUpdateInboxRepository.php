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

    public function recordIfNew(int $updateId): ?TelegramUpdateInbox
    {
        $existing = $this->repository->findOneBy(['updateId' => $updateId]);
        if ($existing !== null) {
            return null;
        }

        $inbox = new TelegramUpdateInbox($updateId);
        $this->entityManager->persist($inbox);
        $this->entityManager->flush();

        return $inbox;
    }
}
