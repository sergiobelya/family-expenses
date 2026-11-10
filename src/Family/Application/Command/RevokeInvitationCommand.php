<?php

namespace App\Family\Application\Command;

use App\Core\Domain\ValueObject\InvitationId;
use App\Core\Domain\ValueObject\UserId;

final readonly class RevokeInvitationCommand
{
    public function __construct(
        public InvitationId $invitationId,
        public UserId $revokedByUserId
    ) {
    }
}
