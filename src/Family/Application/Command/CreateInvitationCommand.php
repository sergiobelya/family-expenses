<?php

namespace App\Family\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class CreateInvitationCommand
{
    public Uuid $familyId;
    public Uuid $createdByUserId;
    public ?int $maxUses;
    public ?int $expiresInHours;

    public function __construct(
        Uuid $familyId,
        ?Uuid $createdByUserId = null,
        ?int $maxUses = 1,
        ?int $expiresInHours = null,
        ?Uuid $userId = null
    ) {
        $this->familyId = $familyId;
        $this->createdByUserId = $createdByUserId ?? $userId ?? throw new \InvalidArgumentException('Creator user ID is required.');
        $this->maxUses = $maxUses;
        $this->expiresInHours = $expiresInHours;
    }
}
