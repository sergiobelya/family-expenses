<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class SwitchActiveFamilyHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(SwitchActiveFamilyCommand $command): User
    {
        $user = $this->userRepository->findById($command->userId);
        if ($user === null) {
            throw UserNotFoundException::withId((string) $command->userId);
        }

        $user->switchActiveFamily($command->activeFamilyId);
        $this->userRepository->save($user);

        return $user;
    }
}
