<?php

namespace App\Presentation\Telegram\ExceptionMapper;

use App\Core\Domain\Exception\ErrorType;
use App\Core\Domain\Exception\InvalidValueException;
use Symfony\Component\Translation\TranslatableMessage;
use Throwable;

/**
 * Maps Core domain exceptions (InvalidValueException) to TranslatableMessage.
 *
 * Owns all ErrorType cases — no other mapper should handle InvalidValueException.
 */
final readonly class CoreExceptionMapper implements ExceptionMapperInterface
{
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof InvalidValueException;
    }

    /**
     * @param InvalidValueException $exception
     */
    public function map(Throwable $exception): TranslatableMessage
    {
        return match ($exception->errorType) {
            ErrorType::FAMILY_NAME_TOO_SHORT => new TranslatableMessage('settings.name_too_short'),
            ErrorType::FAMILY_NAME_TOO_LONG => new TranslatableMessage('settings.name_too_long'),
            ErrorType::CATALOG_NAME_INVALID => new TranslatableMessage('catalog.invalid_name'),
            ErrorType::CURRENCY_CODE_UNSUPPORTED => new TranslatableMessage('settings.invalid_currency'),
            ErrorType::COUNTRY_CODE_INVALID => new TranslatableMessage('settings.invalid_country'),
            ErrorType::TIMEZONE_INVALID => new TranslatableMessage('settings.invalid_timezone'),
            ErrorType::MONEY_AMOUNT_INVALID_FORMAT => new TranslatableMessage('expense.amount.invalid_format'),
            ErrorType::MONEY_AMOUNT_NOT_POSITIVE => new TranslatableMessage('expense.amount.not_positive'),
            ErrorType::MONEY_AMOUNT_TOO_LARGE => new TranslatableMessage(
                'expense.amount.too_large',
                ['%{maxAmount}' => number_format($exception->properties['maxAmount'] ?? 100_000_000, 0, '.', ',')]
            ),
            ErrorType::MONEY_AMOUNT_TOO_PRECISE => new TranslatableMessage(
                'expense.amount.too_precise',
                ['%{precision}' => $exception->properties['maxPrecision'] ?? 2]
            ),
            ErrorType::MONEY_AMOUNT_TOO_PRECISE_ZERO => new TranslatableMessage('expense.amount.too_precise_zero'),
            ErrorType::PURCHASE_DATE_IN_FUTURE => new TranslatableMessage('expense.future_date'),
        };
    }
}
