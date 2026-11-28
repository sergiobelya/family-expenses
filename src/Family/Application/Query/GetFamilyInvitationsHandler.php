<?php

namespace App\Family\Application\Query;

use App\Core\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
readonly class GetFamilyInvitationsHandler
{
    public function __construct(
        private FamilyMemberRepositoryInterface $familyMemberRepository,
        private InvitationRepositoryInterface $invitationRepository
    ) {
    }

    /**
     * @return Invitation[]
     */
    public function __invoke(GetFamilyInvitationsQuery $query): array
    {
        $familyId = $query->familyId;
        $userId = $query->requestedByUserId;
        if (!$this->familyMemberRepository->isMember($familyId, $userId)) {
            throw NotFamilyMemberException::forFamily((string) $query->familyId, (string) $query->requestedByUserId);
        }

        return $this->invitationRepository->findByFamilyId($familyId);
    }
}
