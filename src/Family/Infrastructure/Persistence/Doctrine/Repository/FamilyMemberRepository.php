<?php

namespace App\Family\Infrastructure\Persistence\Doctrine\Repository;

use App\Family\Domain\Enum\FamilyMemberStatus;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final readonly class FamilyMemberRepository implements FamilyMemberRepositoryInterface
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

    public function isMember(FamilyId $familyId, UserId $userId): bool
    {
        return $this->findByFamilyAndUser($familyId, $userId) !== null;
    }

    public function findByFamilyAndUser(FamilyId $familyId, UserId $userId): ?FamilyMember
    {
        return $this->repository->findOneBy([
            'familyId' => $familyId,
            'userId' => $userId,
            'status' => FamilyMemberStatus::ACTIVE,
        ]);
    }

    /**
     * @return FamilyMember[]
     */
    public function findByFamilyId(FamilyId $familyId): array
    {
        return $this->repository->findBy(['familyId' => $familyId, 'status' => FamilyMemberStatus::ACTIVE]);
    }

    /**
     * @return FamilyMember[]
     */
    public function findByUserId(UserId $userId): array
    {
        return $this->repository->findBy(['userId' => $userId, 'status' => FamilyMemberStatus::ACTIVE]);
    }
}
