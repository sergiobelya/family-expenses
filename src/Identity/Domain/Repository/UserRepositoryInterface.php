<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\User;
use App\Core\Domain\ValueObject\UserId;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByTelegramUserId(int $telegramUserId): ?User;
}
