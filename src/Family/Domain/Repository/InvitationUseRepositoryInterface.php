<?php

namespace App\Family\Domain\Repository;

use App\Family\Domain\Entity\InvitationUse;
use Symfony\Component\Uid\Uuid;

interface InvitationUseRepositoryInterface
{
    public function save(InvitationUse $use): void;

    public function countByInvitationId(Uuid $invitationId): int;

    public function hasUserUsedInvitation(Uuid $invitationId, Uuid $userId): bool;
}
