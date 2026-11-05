<?php

namespace App\Identity\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class ChangeUserLanguageCommand
{
    public function __construct(
        public Uuid $userId,
        public string $language
    ) {
    }
}
