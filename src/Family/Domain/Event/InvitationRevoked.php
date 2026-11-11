<?php

namespace App\Family\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\InvitationId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

final readonly class InvitationRevoked implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public InvitationId $invitationId,
        public FamilyId $familyId,
        public UserId $revokedByUserId,
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
        return $this->revokedByUserId;
    }

    public function getEntityType(): string
    {
        return 'invitation';
    }

    public function getEntityId(): string
    {
        return (string) $this->invitationId;
    }

    public function getAction(): string
    {
        return 'invitation.revoked';
    }

    public function getBeforeData(): array
    {
        return ['revoked_at' => null];
    }

    public function getAfterData(): array
    {
        return ['revoked_at' => $this->occurredAt->format(\DateTimeInterface::ATOM)];
    }

}
