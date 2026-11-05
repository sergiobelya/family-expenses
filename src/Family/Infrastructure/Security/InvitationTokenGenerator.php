<?php

namespace App\Family\Infrastructure\Security;

class InvitationTokenGenerator
{
    public function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function hashToken(string $rawToken): string
    {
        return hash('sha256', trim($rawToken));
    }
}
