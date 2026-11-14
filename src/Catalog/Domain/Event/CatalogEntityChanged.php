<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Domain\ValueObject\UuidValueObject;
use DateTimeImmutable;
use DateTimeZone;

abstract readonly class CatalogEntityChanged implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed>|null $beforeData
     * @param array<string, mixed>|null $afterData
     */
    public function __construct(
        private string $action,
        private UuidValueObject $entityId,
        private FamilyId $familyId,
        private UserId $actorUserId,
        private ?array $beforeData,
        private ?array $afterData,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    abstract protected function entityType(): string;

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getFamilyId(): ?FamilyId
    {
        return $this->familyId;
    }

    public function getActorUserId(): UserId
    {
        return $this->actorUserId;
    }

    public function getEntityType(): string
    {
        return $this->entityType();
    }

    public function getEntityId(): string
    {
        return (string) $this->entityId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    public function getAfterData(): ?array
    {
        return $this->afterData;
    }
}
