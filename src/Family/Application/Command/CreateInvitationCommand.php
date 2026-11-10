<?php

namespace App\Family\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class CreateInvitationCommand
{
    public FamilyId $familyId;
    public UserId $createdByUserId;
    public ?int $maxUses;
    public ?int $expiresInHours;

    public function __construct(
        FamilyId $familyId,
        ?UserId $createdByUserId = null,
        ?int $maxUses = 1,
        ?int $expiresInHours = null,
        ?UserId $userId = null
    ) {
        $this->familyId = $familyId;
        $this->createdByUserId = $createdByUserId ?? $userId ?? throw new \InvalidArgumentException('Creator user ID is required.');
        $this->maxUses = $maxUses;
        $this->expiresInHours = $expiresInHours;
    }
}
