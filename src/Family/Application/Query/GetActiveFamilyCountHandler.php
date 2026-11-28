<?php

namespace App\Family\Application\Query;

use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetActiveFamilyCountHandler
{
    public function __construct(private FamilyMemberRepositoryInterface $familyMemberRepository)
    {
    }

    public function __invoke(GetActiveFamilyCountQuery $query): int
    {
        $activeMemberships = array_filter(
            $this->familyMemberRepository->findByUserId($query->userId),
            static fn (FamilyMember $member): bool => $member->isActive()
        );

        return count($activeMemberships);
    }
}
