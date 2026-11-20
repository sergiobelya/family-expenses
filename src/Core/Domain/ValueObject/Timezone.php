<?php

namespace App\Core\Domain\ValueObject;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use DateTimeZone;

final readonly class Timezone
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);
        if ($normalized === '' || !in_array($normalized, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidValueException(ErrorType::TIMEZONE_INVALID);
        }

        $this->value = $normalized;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function default(): self
    {
        return new self('Europe/Warsaw');
    }

    public function toDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
