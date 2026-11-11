<?php

namespace App\Family\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\FamilyMemberId;
use App\Core\Domain\ValueObject\InvitationId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

final readonly class MemberJoined implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public FamilyMemberId $memberId,
        public FamilyId $familyId,
        public UserId $userId,
        public ?InvitationId $invitationId = null,
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

    public function getActorUserId(): UserId
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

    public function getAfterData(): array
    {
        return [
            'id' => (string) $this->memberId,
            'family_id' => (string) $this->familyId,
            'user_id' => (string) $this->userId,
            'invitation_id' => $this->invitationId ? (string) $this->invitationId : null,
        ];
    }

}
