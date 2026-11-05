<?php

namespace App\Identity\Infrastructure\Persistence\Doctrine\Repository;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

class UserRepository implements UserRepositoryInterface
{
    /** @var EntityRepository<User> */
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(User::class);
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
    }

    public function findById(Uuid $id): ?User
    {
        return $this->repository->find($id);
    }

    public function findByTelegramUserId(int $telegramUserId): ?User
    {
        return $this->repository->findOneBy(['telegramUserId' => $telegramUserId]);
    }
}
