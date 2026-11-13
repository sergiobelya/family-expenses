<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Enum\UserLanguage;

final readonly class EnsureUserExistsCommand
{
    public function __construct(
        public int $telegramUserId,
        public string $language = UserLanguage::ENGLISH->value
    ) {
    }
}
