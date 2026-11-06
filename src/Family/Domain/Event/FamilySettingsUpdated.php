<?php

namespace App\Family\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class FamilySettingsUpdated implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $beforeData
     * @param array<string, mixed> $afterData
     */
    public function __construct(
        public Uuid $familyId,
        public array $beforeData,
        public array $afterData,
        public ?Uuid $actorUserId = null,
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
        return 'family';
    }

    public function getEntityId(): string
    {
        return (string) $this->familyId;
    }

    public function getAction(): string
    {
        return 'family.settings_updated';
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
