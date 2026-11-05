<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class EnsureUserExistsHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(EnsureUserExistsCommand $command): User
    {
        $user = $this->userRepository->findByTelegramUserId($command->telegramUserId);
        if ($user !== null) {
            return $user;
        }

        $user = User::create($command->telegramUserId, $command->language ?? 'en');
        $this->userRepository->save($user);

        return $user;
    }
}
