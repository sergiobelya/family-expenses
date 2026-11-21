<?php

namespace App\Family\Application\Command;

use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Infrastructure\Security\InvitationTokenGenerator;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
readonly class CreateInvitationHandler
{
    public function __construct(
        private FamilyMemberRepositoryInterface $familyMemberRepository,
        private InvitationRepositoryInterface $invitationRepository,
        private InvitationTokenGenerator $tokenGenerator
    ) {
    }

    /**
     * @return array{invitation: Invitation, raw_token: string}
     */
    public function __invoke(CreateInvitationCommand $command): array
    {
        $familyId = $command->familyId;
        $createdByUserId = $command->createdByUserId;
        if (!$this->familyMemberRepository->isMember($familyId, $createdByUserId)) {
            throw NotFamilyMemberException::forFamily((string) $command->familyId, (string) $command->createdByUserId);
        }

        $rawToken = $this->tokenGenerator->generateToken();
        $tokenHash = $this->tokenGenerator->hashToken($rawToken);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $hours = $command->expiresInHours ?? Invitation::DEFAULT_EXPIRATION_HOURS;
        $expiresAt = $now->modify(sprintf('+%d hours', $hours));

        $invitation = Invitation::create(
            familyId: $familyId,
            createdByUserId: $createdByUserId,
            tokenHash: $tokenHash,
            expiresAt: $expiresAt,
            maxUses: $command->maxUses,
            now: $now
        );

        $this->invitationRepository->save($invitation);

        return [
            'invitation' => $invitation,
            'raw_token' => $rawToken,
        ];
    }
}
