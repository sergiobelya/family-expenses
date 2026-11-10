<?php

namespace App\Family\Application\Command;

use App\Core\Domain\ValueObject\UserId;

final readonly class JoinFamilyByInvitationCommand
{
    public string $token;
    public UserId $userId;

    public function __construct(
        string $token,
        UserId $userId
    ) {
        $this->token = $token;
        $this->userId = $userId;
    }
}
