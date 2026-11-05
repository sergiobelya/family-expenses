<?php

namespace App\Family\Domain\Exception;

use RuntimeException;

class InvitationRevokedException extends RuntimeException
{
    public static function create(): self
    {
        return new self('This invitation has been revoked.');
    }
}
