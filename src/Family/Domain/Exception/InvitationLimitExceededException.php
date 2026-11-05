<?php

namespace App\Family\Domain\Exception;

use RuntimeException;

class InvitationLimitExceededException extends RuntimeException
{
    public static function create(): self
    {
        return new self('This invitation has reached its maximum usage limit.');
    }
}
