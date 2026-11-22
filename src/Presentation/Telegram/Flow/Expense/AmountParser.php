<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\MoneyAmount;

/**
 * Parses raw user input into a validated MoneyAmount.
 *
 * Handles only the presentation-layer concerns: trimming, comma normalization,
 * and regex-based format validation. Domain validation (precision, max amount,
 * positive check) is delegated to {@see MoneyAmount::fromDecimalWithValidation}.
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
    /**
     * Parses a raw text input and returns a validated MoneyAmount.
     *
     * @throws InvalidValueException if the input is empty, malformed, or fails domain validation
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
        return MoneyAmount::fromDecimalWithValidation(
            $normalized,
            CurrencyCode::fromString($currencyCode)
        );
    }
}
