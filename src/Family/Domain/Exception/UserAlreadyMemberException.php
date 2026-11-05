<?php

namespace App\Family\Domain\Exception;

use RuntimeException;

class UserAlreadyMemberException extends RuntimeException
{
    public static function create(string $familyId, string $userId): self
    {
        return new self(sprintf('User "%s" is already a member of family "%s".', $userId, $familyId));
    }
}
