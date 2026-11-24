<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\ValueObject\MoneyAmount;

/**
 * Formats a decimal money amount string for display.
 *
 * Truncates to currency precision using bcmath and pads trailing zeros
 * without float cast, which loses precision for numbers > 15 digits.
 *
 * Example output: "125.50 USD", "500 ISK".
 */
final readonly class MoneyAmountFormatter
{
    /**
     * Formats an amount for display: "125.50 PLN".
     */
    public function format(string $amount, string $currencyCode): string
    {
        $precision = MoneyAmount::getMaxPrecision($currencyCode);
        if ($precision === 0) {
            // bcadd with scale 0 truncates the integer part incorrectly, so drop
            // the fractional part manually for zero-precision currencies.
            $formatted = explode('.', $amount)[0];
        } else {
            // @phpstan-ignore argument.type
            $formatted = rtrim(rtrim(bcadd($amount, '0', $precision), '0'), '.');
            // Pad trailing zeros to match currency precision without using float cast,
            // which loses precision for numbers > 15 digits (IEEE 754 double limit).
            $parts = explode('.', $formatted);
            $decimal = $parts[1] ?? '';
            $decimal = str_pad($decimal, $precision, '0');
            $formatted = $parts[0] . '.' . $decimal;
        }
        return $formatted . ' ' . $currencyCode;
    }
}
