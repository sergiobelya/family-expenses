<?php

namespace App\Family\Application\Query;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Exception\FamilyNotFoundException;
use App\Family\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
class GetFamilySettingsHandler
{
    public function __construct(
        private FamilyRepositoryInterface $familyRepository,
        private FamilyMemberRepositoryInterface $familyMemberRepository
    ) {
    }

    public function __invoke(GetFamilySettingsQuery $query): Family
    {
        if (!$this->familyMemberRepository->isMember($query->familyId, $query->requestedByUserId)) {
            throw NotFamilyMemberException::forFamily((string) $query->familyId, (string) $query->requestedByUserId);
        }

        $family = $this->familyRepository->findById($query->familyId);
        if ($family === null) {
            throw FamilyNotFoundException::withId((string) $query->familyId);
        }

        return $family;
    }
}
