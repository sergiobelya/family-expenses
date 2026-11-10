<?php

namespace App\Identity\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;

final readonly class SwitchActiveFamilyCommand
{
    public function __construct(
        public UserId $userId,
        public ?FamilyId $activeFamilyId
    ) {
    }
}
