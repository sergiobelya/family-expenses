<?php

namespace App\Family\Application\Command;

use App\Family\Domain\Exception\InvitationNotFoundException;
use App\Family\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class RevokeInvitationHandler
{
    public function __construct(
        private FamilyMemberRepositoryInterface $familyMemberRepository,
        private InvitationRepositoryInterface $invitationRepository
    ) {
    }

    public function __invoke(RevokeInvitationCommand $command): void
    {
        $invitation = $this->invitationRepository->findById($command->invitationId);
        if ($invitation === null) {
            throw InvitationNotFoundException::create();
        }

        if (!$this->familyMemberRepository->isMember($invitation->getFamilyId(), $command->revokedByUserId)) {
            throw NotFamilyMemberException::forFamily((string) $invitation->getFamilyId(), (string) $command->revokedByUserId);
        }

        $invitation->revoke($command->revokedByUserId);
        $this->invitationRepository->save($invitation);
    }
}
