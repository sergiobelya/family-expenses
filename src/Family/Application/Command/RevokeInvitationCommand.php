<?php

namespace App\Family\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class RevokeInvitationCommand
{
    public function __construct(
        public Uuid $invitationId,
        public Uuid $revokedByUserId
    ) {
    }
}
