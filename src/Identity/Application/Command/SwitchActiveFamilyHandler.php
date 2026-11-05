<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;
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
            throw new InvalidArgumentException(sprintf('User with id "%s" was not found.', $command->userId));
        }

        $user->setActiveFamilyId($command->activeFamilyId);
        $this->userRepository->save($user);

        return $user;
    }
}
