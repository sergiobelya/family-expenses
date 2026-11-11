<?php

namespace App\Family\Domain\Repository;

use App\Family\Domain\Entity\InvitationUse;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\InvitationId;
use App\Core\Domain\ValueObject\UserId;

interface InvitationUseRepositoryInterface
{
    public function save(InvitationUse $use): void;

    public function countByInvitationId(FamilyId $familyId, InvitationId $invitationId): int;

    public function hasUserUsedInvitation(FamilyId $familyId, InvitationId $invitationId, UserId $userId): bool;
}
