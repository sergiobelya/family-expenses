<?php

namespace App\Core\Domain\ValueObject;

use DateTimeZone;
use InvalidArgumentException;

final readonly class Timezone
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);
        if ($normalized === '' || !in_array($normalized, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException(sprintf('Invalid IANA timezone identifier: "%s".', $value));
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
