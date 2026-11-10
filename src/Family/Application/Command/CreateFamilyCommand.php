<?php

namespace App\Family\Application\Command;

use App\Core\Domain\ValueObject\UserId;

final readonly class CreateFamilyCommand
{
    public function __construct(
        public UserId $userId,
        public string $name,
        public string $timezone,
        public string $defaultExpenseCurrency,
        public string $reportingCurrency,
        public string $defaultExchangeRateProvider = 'nbp',
        public ?string $countryCode = null
    ) {
    }
}
