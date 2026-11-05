<?php

namespace App\Family\Domain\Entity;

use App\Family\Domain\Event\MemberJoined;
use App\Core\Domain\Aggregate\AggregateRoot;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

class FamilyMember extends AggregateRoot
{
    public const string STATUS_ACTIVE = 'active';

    private Uuid $id;
    private Uuid $familyId;
    private Uuid $userId;
    private string $status;
    private DateTimeImmutable $joinedAt;
    private DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $familyId,
        Uuid $userId,
        string $status = self::STATUS_ACTIVE,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->familyId = $familyId;
        $this->userId = $userId;
        $this->status = $status;
        $this->joinedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->createdAt = $this->joinedAt;
    }

    public static function join(
        Uuid $familyId,
        Uuid $userId,
        ?Uuid $invitationId = null,
        string $status = self::STATUS_ACTIVE,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $member = new self($familyId, $userId, $status, $id, $now);
        $member->recordEvent(new MemberJoined(
            memberId: $member->getId(),
            familyId: $familyId,
            userId: $userId,
            invitationId: $invitationId,
            occurredAt: $member->getJoinedAt()
        ));

        return $member;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFamilyId(): Uuid
    {
        return $this->familyId;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getJoinedAt(): DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
