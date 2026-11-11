<?php

namespace App\Core\Domain\ValueObject;

use InvalidArgumentException;

final readonly class CurrencyCode
{
    private const array SUPPORTED = [
        'PLN', 'UAH', 'EUR', 'USD', 'GBP', 'CZK', 'CHF', 'SEK', 'NOK', 'DKK',
        'RON', 'BGN', 'HUF', 'TRY', 'GEL', 'CAD', 'AUD', 'JPY'
    ];

    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));
        if ($normalized === '' || strlen($normalized) !== 3) {
            throw new InvalidArgumentException('Currency code must contain exactly 3 characters.');
        }

        $this->value = $normalized;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** @return list<string> */
    public static function supportedCodes(): array
    {
        return self::SUPPORTED;
    }
}
