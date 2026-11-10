<?php

namespace App\Identity\Domain\Exception;

use RuntimeException;
use Throwable;

final class UserNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $userId,
        ?Throwable $previous = null
    ) {
        parent::__construct('User was not found.', 0, $previous);
    }

    public static function withId(string $userId, ?Throwable $previous = null): self
    {
        return new self($userId, $previous);
    }
}
