<?php

namespace App\Core\Domain\ValueObject;

use Stringable;
use Symfony\Component\Uid\Uuid;

abstract readonly class UuidValueObject implements Stringable
{
    final public function __construct(protected Uuid $uuid)
    {
    }

    public static function fromString(string $value): static
    {
        return new static(Uuid::fromString($value));
    }

    public static function generate(): static
    {
        return new static(Uuid::v7());
    }

    public function toUuid(): Uuid
    {
        return $this->uuid;
    }

    public function toRfc4122(): string
    {
        return $this->uuid->toRfc4122();
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class && $this->uuid->equals($other->uuid);
    }

    public function __toString(): string
    {
        return (string) $this->uuid;
    }
}
