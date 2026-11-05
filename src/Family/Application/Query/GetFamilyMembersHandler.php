<?php

namespace App\Family\Application\Query;

use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
class GetFamilyMembersHandler
{
    public function __construct(private FamilyMemberRepositoryInterface $familyMemberRepository)
    {
    }

    /**
     * @return FamilyMember[]
     */
    public function __invoke(GetFamilyMembersQuery $query): array
    {
        if (!$this->familyMemberRepository->isMember($query->familyId, $query->requestedByUserId)) {
            throw NotFamilyMemberException::forFamily((string) $query->familyId, (string) $query->requestedByUserId);
        }

        return $this->familyMemberRepository->findByFamilyId($query->familyId);
    }
}
