<?php

namespace App\Family\Domain\Exception;

use RuntimeException;

class FamilyNotFoundException extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Family with id "%s" was not found.', $id));
    }
}
