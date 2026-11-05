<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Repository;

use App\Core\Infrastructure\Persistence\Doctrine\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

class AuditLogRepository
{
    /** @var EntityRepository<AuditLog> */
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(AuditLog::class);
    }

    public function record(AuditLog $auditLog): void
    {
        $this->entityManager->persist($auditLog);
    }

    /**
     * @return AuditLog[]
     */
    public function findByFamilyId(Uuid $familyId, int $limit = 50): array
    {
        return $this->repository->findBy(['familyId' => $familyId], ['createdAt' => 'DESC'], $limit);
    }
}
