<?php

namespace App\Family\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class InvitationRevoked implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public Uuid $invitationId,
        public Uuid $familyId,
        public Uuid $revokedByUserId,
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

    public function getBeforeData(): ?array
    {
        return ['revoked_at' => null];
    }

    public function getAfterData(): ?array
    {
        return ['revoked_at' => $this->occurredAt->format(\DateTimeInterface::ATOM)];
    }

}
