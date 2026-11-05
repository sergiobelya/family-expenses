<?php

namespace App\Family\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class MemberJoined implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public Uuid $memberId,
        public Uuid $familyId,
        public Uuid $userId,
        public ?Uuid $invitationId = null,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getEventName(): string
    {
        return 'family.member_joined';
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
        return $this->userId;
    }

    public function getEntityType(): string
    {
        return 'family_member';
    }

    public function getEntityId(): string
    {
        return (string) $this->memberId;
    }

    public function getAction(): string
    {
        return 'family.joined';
    }

    public function getBeforeData(): ?array
    {
        return null;
    }

    public function getAfterData(): ?array
    {
        return [
            'id' => (string) $this->memberId,
            'family_id' => (string) $this->familyId,
            'user_id' => (string) $this->userId,
            'invitation_id' => $this->invitationId ? (string) $this->invitationId : null,
        ];
    }

    public function toPayload(): array
    {
        return [
            'family_id' => (string) $this->familyId,
            'user_id' => (string) $this->userId,
            'invitation_id' => $this->invitationId ? (string) $this->invitationId : null,
            'joined_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
