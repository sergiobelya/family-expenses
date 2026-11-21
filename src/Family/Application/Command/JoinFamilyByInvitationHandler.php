<?php

namespace App\Family\Application\Command;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Enum\FamilyMemberStatus;
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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
readonly class JoinFamilyByInvitationHandler
{
    public function __construct(
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

        if ($this->familyMemberRepository->isMember($family->getId(), $command->userId)) {
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

        $invitationUse = new InvitationUse(
            invitationId: $invitation->getId(),
            joinedUserId: $command->userId,
            now: $now
        );

        $inserted = $this->invitationUseRepository->insertIfUnderLimit($invitation->getFamilyId(), $invitationUse);

        if (!$inserted) {
            $currentUses = $this->invitationUseRepository->countByInvitationId(
                $invitation->getFamilyId(),
                $invitation->getId()
            );
            if (!$invitation->canBeUsed($currentUses, $now)) {
                throw InvitationLimitExceededException::create();
            }
            throw InvitationExpiredException::create();
        }

        try {
            $member = FamilyMember::join(
                familyId: $family->getId(),
                userId: $command->userId,
                invitationId: $invitation->getId(),
                status: FamilyMemberStatus::ACTIVE,
                now: $now
            );
            $this->familyMemberRepository->save($member);
        } catch (UniqueConstraintViolationException $e) {
            throw InvitationExpiredException::create();
        }

        return [
            'family' => $family,
            'already_member' => false,
        ];
    }
}
