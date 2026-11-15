<?php

namespace App\Family\Domain\Entity;

use App\Family\Domain\Event\FamilyCreated;
use App\Family\Domain\Event\FamilySettingsUpdated;
use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\CountryCode;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Domain\ValueObject\Timezone;
use App\Family\Domain\ValueObject\FamilyName;
use DateTimeImmutable;
use DateTimeZone;

class Family extends AggregateRoot
{
    private FamilyId $id;
    private FamilyName $name;
    private ?CountryCode $countryCode = null;
    private string $timezone;
    private string $defaultExpenseCurrency;
    private string $reportingCurrency;
    private string $defaultExchangeRateProvider;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        string $name,
        Timezone $timezone,
        CurrencyCode $defaultExpenseCurrency,
        CurrencyCode $reportingCurrency,
        string $defaultExchangeRateProvider = 'nbp',
        ?CountryCode $countryCode = null,
        ?FamilyId $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? FamilyId::generate();
        $this->name = FamilyName::fromString($name);
        $this->timezone = (string) $timezone;
        $this->defaultExpenseCurrency = (string) $defaultExpenseCurrency;
        $this->reportingCurrency = (string) $reportingCurrency;
        $this->defaultExchangeRateProvider = trim($defaultExchangeRateProvider);
        $this->countryCode = $countryCode;
        $this->createdAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public static function create(
        string $name,
        Timezone $timezone,
        CurrencyCode $defaultExpenseCurrency,
        CurrencyCode $reportingCurrency,
        string $defaultExchangeRateProvider = 'nbp',
        ?CountryCode $countryCode = null,
        ?UserId $creatorUserId = null,
        ?FamilyId $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $family = new self(
            name: $name,
            timezone: $timezone,
            defaultExpenseCurrency: $defaultExpenseCurrency,
            reportingCurrency: $reportingCurrency,
            defaultExchangeRateProvider: $defaultExchangeRateProvider,
            countryCode: $countryCode,
            id: $id,
            now: $now
        );

        $family->recordEvent(new FamilyCreated(
            familyId: $family->getId(),
            name: (string) $family->getName(),
            timezone: $timezone,
            defaultExpenseCurrency: $defaultExpenseCurrency,
            reportingCurrency: $reportingCurrency,
            defaultExchangeRateProvider: $family->getDefaultExchangeRateProvider(),
            countryCode: $family->getCountryCode() !== null ? (string) $family->getCountryCode() : null,
            creatorUserId: $creatorUserId,
            occurredAt: $family->getCreatedAt()
        ));

        return $family;
    }

    public function getId(): FamilyId
    {
        return $this->id;
    }

    public function getName(): FamilyName
    {
        return $this->name;
    }

    public function getCountryCode(): ?CountryCode
    {
        return $this->countryCode;
    }

    public function getTimezone(): Timezone
    {
        return Timezone::fromString($this->timezone);
    }

    public function getDefaultExpenseCurrency(): CurrencyCode
    {
        return CurrencyCode::fromString($this->defaultExpenseCurrency);
    }

    public function getReportingCurrency(): CurrencyCode
    {
        return CurrencyCode::fromString($this->reportingCurrency);
    }

    public function getDefaultExchangeRateProvider(): string
    {
        return $this->defaultExchangeRateProvider;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateSettings(
        string $name,
        Timezone $timezone,
        CurrencyCode $defaultExpenseCurrency,
        CurrencyCode $reportingCurrency,
        string $defaultExchangeRateProvider = 'nbp',
        ?CountryCode $countryCode = null,
        ?UserId $actorUserId = null,
        ?DateTimeImmutable $now = null
    ): void {
        $beforeData = [
            'name' => (string) $this->name,
            'timezone' => $this->timezone,
            'default_expense_currency' => $this->defaultExpenseCurrency,
            'reporting_currency' => $this->reportingCurrency,
            'default_exchange_rate_provider' => $this->defaultExchangeRateProvider,
            'country_code' => $this->countryCode?->__toString(),
        ];

        $this->name = FamilyName::fromString($name);
        $this->timezone = (string) $timezone;
        $this->defaultExpenseCurrency = (string) $defaultExpenseCurrency;
        $this->reportingCurrency = (string) $reportingCurrency;
        $this->defaultExchangeRateProvider = trim($defaultExchangeRateProvider);
        $this->countryCode = $countryCode;
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $afterData = [
            'name' => (string) $this->name,
            'timezone' => $this->timezone,
            'default_expense_currency' => $this->defaultExpenseCurrency,
            'reporting_currency' => $this->reportingCurrency,
            'default_exchange_rate_provider' => $this->defaultExchangeRateProvider,
            'country_code' => $this->countryCode?->__toString(),
        ];

        $this->recordEvent(new FamilySettingsUpdated(
            familyId: $this->getId(),
            beforeData: $beforeData,
            afterData: $afterData,
            actorUserId: $actorUserId,
            occurredAt: $this->updatedAt
        ));
    }

}
