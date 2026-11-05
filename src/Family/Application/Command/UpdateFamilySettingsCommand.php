<?php

namespace App\Family\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateFamilySettingsCommand
{
    public Uuid $familyId;
    public Uuid $updatedByUserId;
    public ?string $name;
    public ?string $timezone;
    public ?string $defaultExpenseCurrency;
    public ?string $reportingCurrency;
    public ?string $defaultExchangeRateProvider;
    public ?string $countryCode;

    public function __construct(
        Uuid $familyId,
        ?Uuid $updatedByUserId = null,
        ?string $name = null,
        ?string $timezone = null,
        ?string $defaultExpenseCurrency = null,
        ?string $reportingCurrency = null,
        ?string $defaultExchangeRateProvider = null,
        ?string $countryCode = null,
        ?string $country = null,
        ?Uuid $userId = null
    ) {
        $this->familyId = $familyId;
        $this->updatedByUserId = $updatedByUserId ?? $userId ?? throw new \InvalidArgumentException('Actor user ID is required.');
        $this->name = $name;
        $this->timezone = $timezone;
        $this->defaultExpenseCurrency = $defaultExpenseCurrency;
        $this->reportingCurrency = $reportingCurrency;
        $this->defaultExchangeRateProvider = $defaultExchangeRateProvider;
        $this->countryCode = $countryCode ?? $country;
    }
}
