<?php

namespace App\Core\Domain\Exception;

enum ErrorType: string
{
    case FAMILY_NAME_TOO_SHORT = 'family_name_too_short';
    case FAMILY_NAME_TOO_LONG = 'family_name_too_long';
    case CATALOG_NAME_INVALID = 'catalog_name_invalid';
    case CURRENCY_CODE_UNSUPPORTED = 'currency_code_unsupported';
    case COUNTRY_CODE_INVALID = 'country_code_invalid';
    case TIMEZONE_INVALID = 'timezone_invalid';
    case MONEY_AMOUNT_NOT_POSITIVE = 'money_amount_not_positive';
    case MONEY_AMOUNT_INVALID_FORMAT = 'money_amount_invalid_format';
    case PURCHASE_DATE_IN_FUTURE = 'purchase_date_in_future';
}
