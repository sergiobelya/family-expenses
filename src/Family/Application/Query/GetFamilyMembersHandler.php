<?php

namespace App\Family\Application\Query;

use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
readonly class GetFamilyMembersHandler
{
    public function __construct(private FamilyMemberRepositoryInterface $familyMemberRepository)
    {
    }

    /**
     * @return FamilyMember[]
     */
    public function __invoke(GetFamilyMembersQuery $query): array
    {
        $familyId = $query->familyId;
        $userId = $query->requestedByUserId;
        if (!$this->familyMemberRepository->isMember($familyId, $userId)) {
            throw NotFamilyMemberException::forFamily((string) $query->familyId, (string) $query->requestedByUserId);
        }

        return $this->familyMemberRepository->findByFamilyId($familyId);
    }
}
