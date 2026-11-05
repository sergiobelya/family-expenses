<?php

namespace App\Family\Domain\Entity;

use App\Family\Domain\Event\FamilyCreated;
use App\Family\Domain\Event\FamilySettingsUpdated;
use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

class Family extends AggregateRoot
{
    private Uuid $id;
    private string $name;
    private ?string $countryCode = null;
    private string $timezone;
    private string $defaultExpenseCurrency;
    private string $reportingCurrency;
    private string $defaultExchangeRateProvider;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        Timezone $timezone,
        CurrencyCode $defaultExpenseCurrency,
        CurrencyCode $reportingCurrency,
        string $defaultExchangeRateProvider = 'nbp',
        ?string $countryCode = null,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->setNameInternal($name);
        $this->timezone = (string) $timezone;
        $this->defaultExpenseCurrency = (string) $defaultExpenseCurrency;
        $this->reportingCurrency = (string) $reportingCurrency;
        $this->defaultExchangeRateProvider = trim($defaultExchangeRateProvider);
        $this->setCountryCodeInternal($countryCode);
        $this->createdAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public static function create(
        string $name,
        Timezone $timezone,
        CurrencyCode $defaultExpenseCurrency,
        CurrencyCode $reportingCurrency,
        string $defaultExchangeRateProvider = 'nbp',
        ?string $countryCode = null,
        ?Uuid $creatorUserId = null,
        ?Uuid $id = null,
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
            name: $family->getName(),
            timezone: $timezone,
            defaultExpenseCurrency: $defaultExpenseCurrency,
            reportingCurrency: $reportingCurrency,
            defaultExchangeRateProvider: $family->getDefaultExchangeRateProvider(),
            countryCode: $family->getCountryCode(),
            creatorUserId: $creatorUserId,
            occurredAt: $family->getCreatedAt()
        ));

        return $family;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function getCountry(): ?string
    {
        return $this->getCountryCode();
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
        ?string $countryCode = null,
        ?Uuid $actorUserId = null,
        ?DateTimeImmutable $now = null
    ): void {
        $beforeData = [
            'name' => $this->name,
            'timezone' => $this->timezone,
            'default_expense_currency' => $this->defaultExpenseCurrency,
            'reporting_currency' => $this->reportingCurrency,
            'default_exchange_rate_provider' => $this->defaultExchangeRateProvider,
            'country_code' => $this->countryCode,
        ];

        $this->setNameInternal($name);
        $this->timezone = (string) $timezone;
        $this->defaultExpenseCurrency = (string) $defaultExpenseCurrency;
        $this->reportingCurrency = (string) $reportingCurrency;
        $this->defaultExchangeRateProvider = trim($defaultExchangeRateProvider);
        $this->setCountryCodeInternal($countryCode);
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $afterData = [
            'name' => $this->name,
            'timezone' => $this->timezone,
            'default_expense_currency' => $this->defaultExpenseCurrency,
            'reporting_currency' => $this->reportingCurrency,
            'default_exchange_rate_provider' => $this->defaultExchangeRateProvider,
            'country_code' => $this->countryCode,
        ];

        $this->recordEvent(new FamilySettingsUpdated(
            familyId: $this->id,
            beforeData: $beforeData,
            afterData: $afterData,
            actorUserId: $actorUserId,
            occurredAt: $this->updatedAt
        ));
    }

    private function setNameInternal(string $name): void
    {
        $normalized = trim($name);
        if ($normalized === '' || mb_strlen($normalized) > 100) {
            throw new InvalidArgumentException('Family name must not be empty and must be <= 100 characters.');
        }

        $this->name = $normalized;
    }

    private function setCountryCodeInternal(?string $countryCode): void
    {
        if ($countryCode === null || trim($countryCode) === '') {
            $this->countryCode = null;
            return;
        }

        $normalized = strtoupper(trim($countryCode));
        if (strlen($normalized) !== 2) {
            throw new InvalidArgumentException('Country code must be a 2-letter ISO code or null.');
        }

        $this->countryCode = $normalized;
    }
}
