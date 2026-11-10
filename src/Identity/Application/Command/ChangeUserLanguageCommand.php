<?php

namespace App\Identity\Application\Command;

use App\Core\Domain\ValueObject\UserId;

final readonly class ChangeUserLanguageCommand
{
    public function __construct(
        public UserId $userId,
        public string $language
    ) {
    }
}
