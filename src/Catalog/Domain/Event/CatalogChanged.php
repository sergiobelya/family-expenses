<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class CatalogChanged implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed>|null $beforeData
     * @param array<string, mixed>|null $afterData
     */
    public function __construct(
        private string $action,
        private string $entityType,
        private string $entityId,
        private Uuid $familyId,
        private ?Uuid $actorUserId,
        private ?array $beforeData,
        private ?array $afterData,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getFamilyId(): ?Uuid
    {
        return $this->familyId;
    }

    public function getActorUserId(): ?Uuid
    {
        return $this->actorUserId;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
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
