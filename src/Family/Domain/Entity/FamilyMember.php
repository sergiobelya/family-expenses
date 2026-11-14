<?php

namespace App\Family\Domain\Entity;

use App\Family\Domain\Event\MemberJoined;
use App\Family\Domain\Enum\FamilyMemberStatus;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\FamilyMemberId;
use App\Core\Domain\ValueObject\UserId;
use App\Core\Domain\ValueObject\InvitationId;
use App\Core\Domain\Aggregate\AggregateRoot;
use DateTimeImmutable;
use DateTimeZone;

class FamilyMember extends AggregateRoot
{
    private FamilyMemberId $id;
    private FamilyId $familyId;
    private UserId $userId;
    private FamilyMemberStatus $status;
    private DateTimeImmutable $joinedAt;
    private DateTimeImmutable $createdAt;

    private function __construct(
        FamilyId $familyId,
        UserId $userId,
        FamilyMemberStatus $status = FamilyMemberStatus::ACTIVE,
        ?FamilyMemberId $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? FamilyMemberId::generate();
        $this->familyId = $familyId;
        $this->userId = $userId;
        $this->status = $status;
        $this->joinedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->createdAt = $this->joinedAt;
    }

    public static function join(
        FamilyId $familyId,
        UserId $userId,
        ?InvitationId $invitationId = null,
        FamilyMemberStatus $status = FamilyMemberStatus::ACTIVE,
        ?FamilyMemberId $id = null,
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

    public function getId(): FamilyMemberId
    {
        return $this->id;
    }

    public function getFamilyId(): FamilyId
    {
        return $this->familyId;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getStatus(): FamilyMemberStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === FamilyMemberStatus::ACTIVE;
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
