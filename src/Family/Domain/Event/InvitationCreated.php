<?php

namespace App\Family\Domain\Event;

use App\Core\Domain\Event\AuditableEventInterface;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class InvitationCreated implements AuditableEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public Uuid $invitationId,
        public Uuid $familyId,
        public Uuid $createdByUserId,
        public DateTimeImmutable $expiresAt,
        public ?int $maxUses,
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
        return $this->createdByUserId;
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
        return 'invitation.created';
    }

    public function getBeforeData(): ?array
    {
        return null;
    }

    public function getAfterData(): ?array
    {
        return [
            'id' => (string) $this->invitationId,
            'family_id' => (string) $this->familyId,
            'expires_at' => $this->expiresAt->format(\DateTimeInterface::ATOM),
            'max_uses' => $this->maxUses,
        ];
    }

}
