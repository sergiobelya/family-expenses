<?php

namespace App\Family\Domain\ValueObject;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use Stringable;

final readonly class FamilyName implements Stringable
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);
        if (mb_strlen($normalized) < 2) {
            throw new InvalidValueException(ErrorType::FAMILY_NAME_TOO_SHORT);
        }
        if (mb_strlen($normalized) > 100) {
            throw new InvalidValueException(ErrorType::FAMILY_NAME_TOO_LONG);
        }

        return new self($normalized);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
