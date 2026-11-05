<?php

namespace App\Family\Domain\Exception;

use RuntimeException;

class InvitationNotFoundException extends RuntimeException
{
    public static function create(): self
    {
        return new self('Invitation was not found.');
    }
}
