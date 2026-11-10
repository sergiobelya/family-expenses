<?php

namespace App\Family\Infrastructure\Persistence\Doctrine\Repository;

use App\Family\Domain\Entity\InvitationUse;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Core\Domain\ValueObject\InvitationId;
use App\Core\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class InvitationUseRepository implements InvitationUseRepositoryInterface
{
    /** @var EntityRepository<InvitationUse> */
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(InvitationUse::class);
    }

    public function save(InvitationUse $use): void
    {
        $this->entityManager->persist($use);
    }

    public function countByInvitationId(InvitationId $invitationId): int
    {
        return $this->repository->count(['invitationId' => $invitationId]);
    }

    public function hasUserUsedInvitation(InvitationId $invitationId, UserId $userId): bool
    {
        $use = $this->repository->findOneBy([
            'invitationId' => $invitationId,
            'joinedUserId' => $userId,
        ]);

        return $use !== null;
    }
}
