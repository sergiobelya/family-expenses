<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\ValueObject\MoneyAmount;
use NumberFormatter;

/**
 * Formats a decimal money amount string for display.
 *
 * Preserves integer precision using decimal strings and applies the locale's
 * decimal separator without converting amounts to floating-point numbers.
 *
 * Example output: "125.50 USD", "500 ISK".
 */
final readonly class MoneyAmountFormatter
{
    /**
     * Formats an amount for display: "125.50 PLN".
     */
    public function format(string $amount, string $currencyCode, string $locale = 'en'): string
    {
        $precision = MoneyAmount::getMaxPrecision($currencyCode);
        [$integer, $fraction] = array_pad(explode('.', str_replace(',', '.', $amount), 2), 2, '');
        $formatted = $precision === 0
            ? $integer
            : $integer . '.' . str_pad(substr($fraction, 0, $precision), $precision, '0');

        return $this->localizeDecimalSeparator($formatted, $locale) . ' ' . $currencyCode;
    }

    /**
     * Preserves excess fractional digits and returns only the localized number, unlike format() which applies currency precision and appends the currency code.
     */
    public function formatNumber(string $amount, string $currencyCode, string $locale = 'en'): string
    {
        $precision = MoneyAmount::getMaxPrecision($currencyCode);
        [$integer, $fraction] = array_pad(explode('.', str_replace(',', '.', $amount), 2), 2, '');
        $significantFraction = rtrim($fraction, '0');

        if (strlen($significantFraction) > $precision) {
            $formatted = $integer . ($fraction === '' ? '' : '.' . $fraction);
        } elseif ($precision === 0) {
            $formatted = $integer;
        } else {
            $formatted = $integer . '.' . str_pad(substr($fraction, 0, $precision), $precision, '0');
        }

        return $this->localizeDecimalSeparator($formatted, $locale);
    }

    private function localizeDecimalSeparator(string $amount, string $locale): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $decimalSeparator = $formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);

        return str_replace('.', $decimalSeparator, $amount);
    }
}
