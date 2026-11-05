<?php

namespace App\Identity\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class UserActiveFamilySwitched implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public Uuid $userId,
        public ?Uuid $oldFamilyId,
        public ?Uuid $newFamilyId,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getEventName(): string
    {
        return 'user.active_family_switched';
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getFamilyId(): ?Uuid
    {
        return $this->newFamilyId ?? $this->oldFamilyId;
    }

    public function getActorUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function getEntityType(): string
    {
        return 'user';
    }

    public function getEntityId(): string
    {
        return (string) $this->userId;
    }

    public function getAction(): string
    {
        return 'user.active_family_switched';
    }

    public function getBeforeData(): ?array
    {
        return ['active_family_id' => $this->oldFamilyId ? (string) $this->oldFamilyId : null];
    }

    public function getAfterData(): ?array
    {
        return ['active_family_id' => $this->newFamilyId ? (string) $this->newFamilyId : null];
    }

    public function toPayload(): array
    {
        return [
            'user_id' => (string) $this->userId,
            'old_family_id' => $this->oldFamilyId ? (string) $this->oldFamilyId : null,
            'new_family_id' => $this->newFamilyId ? (string) $this->newFamilyId : null,
        ];
    }
}
