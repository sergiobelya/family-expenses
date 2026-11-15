<?php

namespace App\Family\Domain\Repository;

use App\Family\Domain\Entity\InvitationUse;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\InvitationId;
use App\Core\Domain\ValueObject\UserId;

interface InvitationUseRepositoryInterface
{
    public function countByInvitationId(FamilyId $familyId, InvitationId $invitationId): int;

    public function hasUserUsedInvitation(FamilyId $familyId, InvitationId $invitationId, UserId $userId): bool;

    /**
     * Atomically insert an invitation use only if the invitation's max_uses limit has not been reached.
     *
     * Returns true if the row was inserted successfully.
     * Returns false if the insert failed — either because the limit was reached or for other reasons
     * (e.g., the invitation was concurrently deleted). Callers should verify the actual cause.
     */
    public function insertIfUnderLimit(FamilyId $familyId, InvitationUse $use): bool;
}
