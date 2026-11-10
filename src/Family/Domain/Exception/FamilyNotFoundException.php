<?php

namespace App\Family\Domain\Exception;

use RuntimeException;

class FamilyNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $familyId,
        ?\Throwable $previous = null
    ) {
        parent::__construct('Family was not found.', 0, $previous);
    }

    public static function withId(string $id, ?\Throwable $previous = null): self
    {
        return new self($id, $previous);
    }

}
