<?php

namespace App\Family\Application\Command;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Entity\InvitationUse;
use App\Family\Domain\Exception\FamilyNotFoundException;
use App\Family\Domain\Exception\InvitationExpiredException;
use App\Family\Domain\Exception\InvitationLimitExceededException;
use App\Family\Domain\Exception\InvitationNotFoundException;
use App\Family\Domain\Exception\InvitationRevokedException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Family\Infrastructure\Security\InvitationTokenGenerator;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class JoinFamilyByInvitationHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private FamilyRepositoryInterface $familyRepository,
        private FamilyMemberRepositoryInterface $familyMemberRepository,
        private InvitationRepositoryInterface $invitationRepository,
        private InvitationUseRepositoryInterface $invitationUseRepository,
        private InvitationTokenGenerator $tokenGenerator
    ) {
    }

    /**
     * @return array{family: Family, already_member: bool}
     */
    public function __invoke(JoinFamilyByInvitationCommand $command): array
    {
        $user = $this->userRepository->findById($command->userId);
        if ($user === null) {
            throw new InvalidArgumentException(sprintf('User with id "%s" was not found.', $command->userId));
        }

        $tokenHash = $this->tokenGenerator->hashToken($command->token);
        $invitation = $this->invitationRepository->findByTokenHash($tokenHash);
        if ($invitation === null) {
            throw InvitationNotFoundException::create();
        }

        $family = $this->familyRepository->findById($invitation->getFamilyId());
        if ($family === null) {
            throw FamilyNotFoundException::withId((string) $invitation->getFamilyId());
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($this->familyMemberRepository->isMember($family->getId(), $user->getId())) {
            $user->setActiveFamilyId($family->getId(), $now);
            $this->userRepository->save($user);

            return [
                'family' => $family,
                'already_member' => true,
            ];
        }

        if ($invitation->isRevoked()) {
            throw InvitationRevokedException::create();
        }

        if ($invitation->isExpired($now)) {
            throw InvitationExpiredException::create();
        }

        $currentUses = $this->invitationUseRepository->countByInvitationId($invitation->getId());
        if (!$invitation->canBeUsed($currentUses, $now)) {
            throw InvitationLimitExceededException::create();
        }

        $invitationUse = new InvitationUse(
            invitationId: $invitation->getId(),
            joinedUserId: $user->getId(),
            now: $now
        );
        $this->invitationUseRepository->save($invitationUse);

        $member = FamilyMember::join(
            familyId: $family->getId(),
            userId: $user->getId(),
            invitationId: $invitation->getId(),
            status: FamilyMember::STATUS_ACTIVE,
            now: $now
        );
        $this->familyMemberRepository->save($member);

        $user->setActiveFamilyId($family->getId(), $now);
        $this->userRepository->save($user);

        return [
            'family' => $family,
            'already_member' => false,
        ];
    }
}
