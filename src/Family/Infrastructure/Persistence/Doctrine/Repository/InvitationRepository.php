<?php

namespace App\Family\Infrastructure\Persistence\Doctrine\Repository;

use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

class InvitationRepository implements InvitationRepositoryInterface
{
    /** @var EntityRepository<Invitation> */
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(Invitation::class);
    }

    public function save(Invitation $invitation): void
    {
        $this->entityManager->persist($invitation);
    }

    public function findById(Uuid $id): ?Invitation
    {
        return $this->repository->find($id);
    }

    public function findByTokenHash(string $tokenHash): ?Invitation
    {
        return $this->repository->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * @return Invitation[]
     */
    public function findByFamilyId(Uuid $familyId): array
    {
        return $this->repository->findBy(['familyId' => $familyId], ['createdAt' => 'DESC']);
    }
}
