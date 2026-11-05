<?php

namespace App\Family\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class JoinFamilyByInvitationCommand
{
    public string $token;
    public Uuid $userId;

    public function __construct(
        string $token,
        Uuid $userId
    ) {
        $this->token = $token;
        $this->userId = $userId;
    }
}
