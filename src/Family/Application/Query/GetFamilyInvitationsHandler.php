<?php

namespace App\Family\Application\Query;

use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
class GetFamilyInvitationsHandler
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
        if (!$this->familyMemberRepository->isMember($query->familyId, $query->requestedByUserId)) {
            throw NotFamilyMemberException::forFamily((string) $query->familyId, (string) $query->requestedByUserId);
        }

        return $this->invitationRepository->findByFamilyId($query->familyId);
    }
}
