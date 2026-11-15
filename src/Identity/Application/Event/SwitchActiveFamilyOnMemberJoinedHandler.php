<?php

namespace App\Identity\Application\Event;

use App\Family\Domain\Event\MemberJoined;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'event.bus')]
class SwitchActiveFamilyOnMemberJoinedHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(MemberJoined $event): void
    {
        $user = $this->userRepository->findById($event->userId);
        if ($user === null) {
            return;
        }

        $user->switchActiveFamily($event->familyId);
        $this->userRepository->save($user);
    }
}
