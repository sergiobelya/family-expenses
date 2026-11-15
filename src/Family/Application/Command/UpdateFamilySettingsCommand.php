<?php

namespace App\Family\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class UpdateFamilySettingsCommand
{
    public FamilyId $familyId;
    public UserId $updatedByUserId;
    public ?string $name;
    public ?string $timezone;
    public ?string $defaultExpenseCurrency;
    public ?string $reportingCurrency;
    public ?string $defaultExchangeRateProvider;
    public ?string $countryCode;

    public function __construct(
        FamilyId $familyId,
        ?UserId $updatedByUserId = null,
        ?string $name = null,
        ?string $timezone = null,
        ?string $defaultExpenseCurrency = null,
        ?string $reportingCurrency = null,
        ?string $defaultExchangeRateProvider = null,
        ?string $countryCode = null,
        ?UserId $userId = null
    ) {
        $this->familyId = $familyId;
        $this->updatedByUserId = $updatedByUserId ?? $userId ?? throw new \InvalidArgumentException('Actor user ID is required.');
        $this->name = $name;
        $this->timezone = $timezone;
        $this->defaultExpenseCurrency = $defaultExpenseCurrency;
        $this->reportingCurrency = $reportingCurrency;
        $this->defaultExchangeRateProvider = $defaultExchangeRateProvider;
        $this->countryCode = $countryCode;
    }
}
