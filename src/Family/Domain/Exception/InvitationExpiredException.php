<?php

namespace App\Family\Domain\Exception;

use RuntimeException;

class InvitationExpiredException extends RuntimeException
{
    public static function create(): self
    {
        return new self('This invitation has expired.');
    }
}
