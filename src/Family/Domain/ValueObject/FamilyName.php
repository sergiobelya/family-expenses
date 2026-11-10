<?php

namespace App\Family\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

final readonly class FamilyName implements Stringable
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);
        if ($normalized === '' || mb_strlen($normalized) > 100) {
            throw new InvalidArgumentException('Family name must not be empty and must be at most 100 characters.');
        }

        return new self($normalized);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
