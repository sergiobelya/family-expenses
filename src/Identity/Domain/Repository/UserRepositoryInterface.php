<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\User;
use Symfony\Component\Uid\Uuid;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(Uuid $id): ?User;

    public function findByTelegramUserId(int $telegramUserId): ?User;
}
