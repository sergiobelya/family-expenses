<?php

namespace App\Identity\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

final readonly class UserActiveFamilySwitched implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public UserId $userId,
        public ?FamilyId $oldFamilyId,
        public ?FamilyId $newFamilyId,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getFamilyId(): ?FamilyId
    {
        return $this->newFamilyId ?? $this->oldFamilyId;
    }

    public function getActorUserId(): UserId
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

    public function getBeforeData(): array
    {
        return ['active_family_id' => $this->oldFamilyId ? (string) $this->oldFamilyId : null];
    }

    public function getAfterData(): array
    {
        return ['active_family_id' => $this->newFamilyId ? (string) $this->newFamilyId : null];
    }

}
