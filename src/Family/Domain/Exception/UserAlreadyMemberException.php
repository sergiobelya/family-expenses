<?php

namespace App\Family\Domain\Exception;

use RuntimeException;

class UserAlreadyMemberException extends RuntimeException
{
    public function __construct(
        public readonly string $familyId,
        public readonly string $userId,
        ?\Throwable $previous = null
    ) {
        parent::__construct('User is already a member of this family.', 0, $previous);
    }

    public static function create(
        string $familyId,
        string $userId,
        ?\Throwable $previous = null
    ): self
    {
        return new self($familyId, $userId, $previous);
    }

}
