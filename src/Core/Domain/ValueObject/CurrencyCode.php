<?php

namespace App\Core\Domain\ValueObject;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;

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
        if (!in_array($normalized, self::SUPPORTED, true)) {
            throw new InvalidValueException(ErrorType::CURRENCY_CODE_UNSUPPORTED);
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
