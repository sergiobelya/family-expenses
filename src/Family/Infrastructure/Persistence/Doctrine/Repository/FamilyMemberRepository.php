<?php

namespace App\Family\Infrastructure\Persistence\Doctrine\Repository;

use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

class FamilyMemberRepository implements FamilyMemberRepositoryInterface
{
    /** @var EntityRepository<FamilyMember> */
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(FamilyMember::class);
    }

    public function save(FamilyMember $member): void
    {
        $this->entityManager->persist($member);
    }

    public function isMember(Uuid $familyId, Uuid $userId): bool
    {
        return $this->findByFamilyAndUser($familyId, $userId) !== null;
    }

    public function findByFamilyAndUser(Uuid $familyId, Uuid $userId): ?FamilyMember
    {
        return $this->repository->findOneBy([
            'familyId' => $familyId,
            'userId' => $userId,
            'status' => FamilyMember::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return FamilyMember[]
     */
    public function findByFamilyId(Uuid $familyId): array
    {
        return $this->repository->findBy(['familyId' => $familyId, 'status' => FamilyMember::STATUS_ACTIVE]);
    }

    /**
     * @return FamilyMember[]
     */
    public function findByUserId(Uuid $userId): array
    {
        return $this->repository->findBy(['userId' => $userId, 'status' => FamilyMember::STATUS_ACTIVE]);
    }
}
