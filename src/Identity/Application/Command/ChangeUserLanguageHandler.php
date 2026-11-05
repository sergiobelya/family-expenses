<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class ChangeUserLanguageHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(ChangeUserLanguageCommand $command): User
    {
        $user = $this->userRepository->findById($command->userId);
        if ($user === null) {
            throw new InvalidArgumentException(sprintf('User with id "%s" was not found.', $command->userId));
        }

        $user->setLanguage($command->language);
        $this->userRepository->save($user);

        return $user;
    }
}
