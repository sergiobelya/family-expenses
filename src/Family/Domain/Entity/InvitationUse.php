<?php

namespace App\Family\Domain\Entity;

use App\Core\Domain\ValueObject\InvitationId;
use App\Core\Domain\ValueObject\InvitationUseId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

final class InvitationUse
{
    private InvitationUseId $id;
    private InvitationId $invitationId;
    private UserId $joinedUserId;
    private DateTimeImmutable $usedAt;

    public function __construct(
        InvitationId $invitationId,
        UserId $joinedUserId,
        ?InvitationUseId $id = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->id = $id ?? InvitationUseId::generate();
        $this->invitationId = $invitationId;
        $this->joinedUserId = $joinedUserId;
        $this->usedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): InvitationUseId
    {
        return $this->id;
    }

    public function getInvitationId(): InvitationId
    {
        return $this->invitationId;
    }

    public function getJoinedUserId(): UserId
    {
        return $this->joinedUserId;
    }

    public function getUsedAt(): DateTimeImmutable
    {
        return $this->usedAt;
    }
}
