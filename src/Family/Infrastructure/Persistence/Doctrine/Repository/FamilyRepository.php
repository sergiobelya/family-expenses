<?php

namespace App\Family\Infrastructure\Persistence\Doctrine\Repository;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Core\Domain\ValueObject\FamilyId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final readonly class FamilyRepository implements FamilyRepositoryInterface
{
    /** @var EntityRepository<Family> */
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(Family::class);
    }

    public function save(Family $family): void
    {
        $this->entityManager->persist($family);
    }

    public function findById(FamilyId $id): ?Family
    {
        return $this->repository->find($id);
    }

    /**
     * @param FamilyId[] $ids
     * @return Family[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->repository->findBy(['id' => $ids]);
    }
}
