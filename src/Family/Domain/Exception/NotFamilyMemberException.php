<?php

namespace App\Family\Domain\Exception;

use RuntimeException;

class NotFamilyMemberException extends RuntimeException
{
    public static function forFamily(string $familyId, string $userId): self
    {
        return new self(sprintf('User "%s" is not an active member of family "%s".', $userId, $familyId));
    }
}
