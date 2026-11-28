<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\MoneyAmount;
use App\Expense\Application\Validation\ExpenseAmountValidator;

/**
 * Parses raw user input into a validated MoneyAmount.
 *
 * Handles only the presentation-layer concerns: trimming, comma normalization,
 * and regex-based format validation. Domain amount rules are enforced by the
 * MoneyAmount value object; the configured maximum is checked by the shared
 * Application validator.
 *
 * Per add_expense.md §6.1: accepts ASCII digits with one optional decimal
 * separator (. or ,) followed by at least one digit. No thousands separators,
 * signs, exponents, currency codes, or Unicode digits.
 *
 * Follows the same pattern as {@see CatalogName::fromString}: returns a VO on
 * success, throws {@see InvalidValueException} on validation failure.
 */
final readonly class AmountParser
{
    public function __construct(private ExpenseAmountValidator $amountValidator)
    {
    }

    /**
     * Parses a raw text input and returns a validated MoneyAmount.
     *
     * @throws InvalidValueException if the input is malformed or fails amount validation
     */
    public function parse(string $raw, string $currencyCode): MoneyAmount
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            throw new InvalidValueException(ErrorType::MONEY_AMOUNT_INVALID_FORMAT);
        }

        // Normalize comma to dot
        $normalized = str_replace(',', '.', $trimmed);

        // Must match: digits with at most one dot, and if dot exists, at least one digit after it
        if (preg_match('/^\d+(\.\d+)?$/', $normalized) !== 1) {
            throw new InvalidValueException(ErrorType::MONEY_AMOUNT_INVALID_FORMAT);
        }

        // After regex check, $normalized is guaranteed to be a numeric-string (digits with optional decimal)
        $amount = MoneyAmount::fromDecimalWithValidation(
            $normalized,
            CurrencyCode::fromString($currencyCode)
        );
        $this->amountValidator->validate($amount);

        return $amount;
    }

    public function exceedsCurrencyPrecision(string $amount, string $currencyCode): bool
    {
        $normalized = str_replace(',', '.', trim($amount));
        $fraction = explode('.', $normalized, 2)[1] ?? '';

        return strlen($fraction) > MoneyAmount::getMaxPrecision(
            (string) CurrencyCode::fromString($currencyCode)
        );
    }
}
