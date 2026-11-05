<?php

namespace App\Identity\Application\Query;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
class GetUserByTelegramIdHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(GetUserByTelegramIdQuery $query): ?User
    {
        return $this->userRepository->findByTelegramUserId($query->telegramUserId);
    }
}
