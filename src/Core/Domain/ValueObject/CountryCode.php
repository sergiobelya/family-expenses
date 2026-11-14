<?php

namespace App\Core\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

final readonly class CountryCode implements Stringable
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));
        if (preg_match('/^[A-Z]{2}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Country code must be a two-letter ISO code.');
        }

        return new self($normalized);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
