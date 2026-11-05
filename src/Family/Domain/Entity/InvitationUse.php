<?php

namespace App\Family\Domain\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

class InvitationUse
{
    private Uuid $id;
    private Uuid $invitationId;
    private Uuid $joinedUserId;
    private DateTimeImmutable $usedAt;

    public function __construct(
        Uuid $invitationId,
        Uuid $joinedUserId,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->invitationId = $invitationId;
        $this->joinedUserId = $joinedUserId;
        $this->usedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getInvitationId(): Uuid
    {
        return $this->invitationId;
    }

    public function getJoinedUserId(): Uuid
    {
        return $this->joinedUserId;
    }

    public function getUsedAt(): DateTimeImmutable
    {
        return $this->usedAt;
    }
}
