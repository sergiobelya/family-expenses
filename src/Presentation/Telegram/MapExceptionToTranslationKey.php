<?php

namespace App\Presentation\Telegram;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;

final readonly class MapExceptionToTranslationKey
{
    public function __invoke(InvalidValueException $exception): string
    {
        return match ($exception->errorType) {
            ErrorType::FAMILY_NAME_TOO_SHORT => 'settings.name_too_short',
            ErrorType::FAMILY_NAME_TOO_LONG => 'settings.name_too_long',
            ErrorType::CATALOG_NAME_INVALID => 'catalog.invalid_name',
            ErrorType::CURRENCY_CODE_UNSUPPORTED => 'settings.invalid_currency',
            ErrorType::COUNTRY_CODE_INVALID => 'settings.invalid_country',
            ErrorType::TIMEZONE_INVALID => 'settings.invalid_timezone',
            ErrorType::MONEY_AMOUNT_INVALID_FORMAT => 'expense.amount.invalid_format',
            ErrorType::MONEY_AMOUNT_NOT_POSITIVE => 'expense.amount.not_positive',
            ErrorType::MONEY_AMOUNT_TOO_LARGE => 'expense.amount.too_large',
            ErrorType::MONEY_AMOUNT_TOO_PRECISE => 'expense.amount.too_precise',
            ErrorType::MONEY_AMOUNT_TOO_PRECISE_ZERO => 'expense.amount.too_precise_zero',
            ErrorType::PURCHASE_DATE_IN_FUTURE => 'expense.future_date',
        };
    }
}
