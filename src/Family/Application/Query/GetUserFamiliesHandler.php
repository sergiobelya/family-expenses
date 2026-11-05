<?php

namespace App\Family\Application\Query;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
class GetUserFamiliesHandler
{
    public function __construct(
        private FamilyMemberRepositoryInterface $familyMemberRepository,
        private FamilyRepositoryInterface $familyRepository
    ) {
    }

    /**
     * @return Family[]
     */
    public function __invoke(GetUserFamiliesQuery $query): array
    {
        $memberships = $this->familyMemberRepository->findByUserId($query->userId);
        if (empty($memberships)) {
            return [];
        }

        $familyIds = array_map(fn (FamilyMember $member) => $member->getFamilyId(), $memberships);

        return $this->familyRepository->findByIds($familyIds);
    }
}
