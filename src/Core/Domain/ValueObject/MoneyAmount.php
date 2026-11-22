<?php

namespace App\Core\Domain\ValueObject;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use Money\Exception as MoneyException;
use Money\Money;
use Throwable;

final readonly class MoneyAmount
{
    /**
     * Currency code => minor unit exponent (subunits).
     */
    private const array MINOR_UNIT_EXPONENT = [
        'ALL' => 2,
        'AMD' => 2,
        'AZN' => 2,
        'BAM' => 2,
        'CAD' => 2,
        'CHF' => 2,
        'CZK' => 2,
        'DKK' => 2,
        'EUR' => 2,
        'GBP' => 2,
        'GEL' => 2,
        'HUF' => 2,
        'ISK' => 0,
        'KZT' => 2,
        'MDL' => 2,
        'MKD' => 2,
        'NOK' => 2,
        'PLN' => 2,
        'RON' => 2,
        'RSD' => 2,
        'SEK' => 2,
        'TRY' => 2,
        'UAH' => 2,
        'USD' => 2,
    ];

    private Money $money;

    /**
     * @throws InvalidValueException if the amount is zero or negative
     */
    public function __construct(Money $money)
    {
        if (!$money->isPositive()) {
            throw new InvalidValueException(ErrorType::MONEY_AMOUNT_NOT_POSITIVE);
        }
        $this->money = $money;
    }

    /**
     * Create from a decimal string (e.g. "100.50" = 100.50 in major units).
     * The value is converted to minor units (cents) internally.
     *
     * @param numeric-string $amount
     * @throws InvalidValueException if the amount is zero, negative, or not a valid number
     */
    public static function fromDecimal(string $amount, CurrencyCode $currency): self
    {
        try {
            $exponent = self::MINOR_UNIT_EXPONENT[$currency->value] ?? 2;
            /** @var numeric-string $multiplier */
            $multiplier = bcpow('10', (string) $exponent, 0);
            /** @var numeric-string $minorUnits */
            $minorUnits = bcmul($amount, $multiplier, 0);
            $money = new Money($minorUnits, $currency->toMoneyCurrency());
        } catch (MoneyException|\ValueError) {
            throw new InvalidValueException(ErrorType::MONEY_AMOUNT_INVALID_FORMAT);
        }
        return new self($money);
    }

    /**
     * Create from minor units (e.g. 10050 = 100.50 PLN).
     *
     * @throws InvalidValueException if the amount is zero or negative
     */
    public static function fromInt(int $amountInMinorUnits, CurrencyCode $currency): self
    {
        $money = new Money($amountInMinorUnits, $currency->toMoneyCurrency());
        return new self($money);
    }

    /**
     * Returns the amount as a numeric string in minor units (e.g. "10050" = 100.50 PLN).
     *
     * @return numeric-string
     */
    public function getMinorUnits(): string
    {
        return $this->money->getAmount();
    }

    public function getCurrency(): CurrencyCode
    {
        return CurrencyCode::fromString($this->money->getCurrency()->getCode());
    }

    public function getMoney(): Money
    {
        return $this->money;
    }

    public function add(self $other): self
    {
        return new self($this->money->add($other->money));
    }

    public function subtract(self $other): self
    {
        return new self($this->money->subtract($other->money));
    }

    public function isZero(): bool
    {
        return $this->money->isZero();
    }

    public function isPositive(): bool
    {
        return $this->money->isPositive();
    }

    public function isNegative(): bool
    {
        return $this->money->isNegative();
    }

    public function greaterThan(self $other): bool
    {
        return $this->money->greaterThan($other->money);
    }

    public function lessThan(self $other): bool
    {
        return $this->money->lessThan($other->money);
    }

    public function equals(self $other): bool
    {
        return $this->money->equals($other->money);
    }
}
