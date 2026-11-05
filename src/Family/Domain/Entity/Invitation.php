<?php

namespace App\Family\Domain\Entity;

use App\Family\Domain\Event\InvitationCreated;
use App\Family\Domain\Event\InvitationRevoked;
use App\Core\Domain\Aggregate\AggregateRoot;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

class Invitation extends AggregateRoot
{
    public const int DEFAULT_EXPIRATION_HOURS = 48;

    private Uuid $id;
    private Uuid $familyId;
    private string $tokenHash;
    private Uuid $createdByUserId;
    private DateTimeImmutable $expiresAt;
    private ?DateTimeImmutable $revokedAt = null;
    private ?int $maxUses = null;
    private DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $familyId,
        Uuid $createdByUserId,
        string $tokenHash,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUses = null,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->familyId = $familyId;
        $this->createdByUserId = $createdByUserId;
        $this->tokenHash = trim($tokenHash);
        $this->createdAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->expiresAt = $expiresAt ?? $this->createdAt->modify(sprintf('+%d hours', self::DEFAULT_EXPIRATION_HOURS));
        if ($this->expiresAt <= $this->createdAt) {
            throw new InvalidArgumentException('Expiration time must be in the future.');
        }

        if ($maxUses !== null && $maxUses < 1) {
            throw new InvalidArgumentException('maxUses must be at least 1 or null (unlimited).');
        }
        $this->maxUses = $maxUses;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function create(
        Uuid $familyId,
        Uuid $createdByUserId,
        string $tokenHash,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUses = null,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $invitation = new self($familyId, $createdByUserId, $tokenHash, $expiresAt, $maxUses, $id, $now);
        $invitation->recordEvent(new InvitationCreated(
            invitationId: $invitation->getId(),
            familyId: $familyId,
            createdByUserId: $createdByUserId,
            expiresAt: $invitation->getExpiresAt(),
            maxUses: $maxUses,
            occurredAt: $invitation->getCreatedAt()
        ));

        return $invitation;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFamilyId(): Uuid
    {
        return $this->familyId;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getCreatedByUserId(): Uuid
    {
        return $this->createdByUserId;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        $current = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $current >= $this->expiresAt;
    }

    public function isActive(?DateTimeImmutable $now = null): bool
    {
        return !$this->isRevoked() && !$this->isExpired($now);
    }

    public function canBeUsed(int $currentUsesCount, ?DateTimeImmutable $now = null): bool
    {
        if ($this->isRevoked()) {
            return false;
        }

        if ($this->isExpired($now)) {
            return false;
        }

        if ($this->maxUses !== null && $currentUsesCount >= $this->maxUses) {
            return false;
        }

        return true;
    }

    public function revoke(?Uuid $revokedByUserId = null, ?DateTimeImmutable $now = null): void
    {
        if ($this->revokedAt === null) {
            $this->revokedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $this->recordEvent(new InvitationRevoked(
                invitationId: $this->id,
                familyId: $this->familyId,
                revokedByUserId: $revokedByUserId ?? $this->createdByUserId,
                occurredAt: $this->revokedAt
            ));
        }
    }
}
