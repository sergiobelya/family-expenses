<?php

namespace App\Family\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

final readonly class FamilyCreated implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public FamilyId $familyId,
        public string $name,
        public Timezone $timezone,
        public CurrencyCode $defaultExpenseCurrency,
        public CurrencyCode $reportingCurrency,
        public string $defaultExchangeRateProvider,
        public ?string $countryCode,
        public ?UserId $creatorUserId = null,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getFamilyId(): FamilyId
    {
        return $this->familyId;
    }

    public function getActorUserId(): ?UserId
    {
        return $this->creatorUserId;
    }

    public function getEntityType(): string
    {
        return 'family';
    }

    public function getEntityId(): string
    {
        return (string) $this->familyId;
    }

    public function getAction(): string
    {
        return 'family.created';
    }

    public function getBeforeData(): ?array
    {
        return null;
    }

    public function getAfterData(): array
    {
        return [
            'id' => (string) $this->familyId,
            'name' => $this->name,
            'timezone' => (string) $this->timezone,
            'default_expense_currency' => (string) $this->defaultExpenseCurrency,
            'reporting_currency' => (string) $this->reportingCurrency,
            'default_exchange_rate_provider' => $this->defaultExchangeRateProvider,
            'country_code' => $this->countryCode,
        ];
    }

}
