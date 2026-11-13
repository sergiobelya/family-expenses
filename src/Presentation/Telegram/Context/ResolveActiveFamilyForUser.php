<?php

declare(strict_types=1);

namespace App\Presentation\Telegram\Context;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;

class ResolveActiveFamilyForUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private FamilyRepositoryInterface $familyRepository,
        private FamilyMemberRepositoryInterface $familyMemberRepository
    ) {}

    public function __invoke(User $user): ?Family
    {
        $activeFamily = null;
        $activeFamilyId = $user->getActiveFamilyId();

        if ($activeFamilyId !== null) {
            $family = $this->familyRepository->findById($activeFamilyId);
            if ($family !== null) {
                $member = $this->familyMemberRepository->findByFamilyAndUser(
                    $family->getId(),
                    $user->getId()
                );
                if ($member !== null && $member->isActive()) {
                    $activeFamily = $family;
                } else {
                    // Stale family membership
                    $user->switchActiveFamily(null);
                    $this->userRepository->save($user);
                }
            } else {
                $user->switchActiveFamily(null);
                $this->userRepository->save($user);
            }
        }

        return $activeFamily;
    }
}
