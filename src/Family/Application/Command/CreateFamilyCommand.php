<?php

namespace App\Family\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class CreateFamilyCommand
{
    public function __construct(
        public Uuid $userId,
        public string $name,
        public string $timezone,
        public string $defaultExpenseCurrency,
        public string $reportingCurrency,
        public string $defaultExchangeRateProvider = 'nbp',
        public ?string $countryCode = null
    ) {
    }
}
