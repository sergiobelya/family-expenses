<?php

namespace App\Identity\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class SwitchActiveFamilyCommand
{
    public function __construct(
        public Uuid $userId,
        public ?Uuid $activeFamilyId
    ) {
    }
}
