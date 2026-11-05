<?php

namespace App\Identity\Application\Command;

final readonly class EnsureUserExistsCommand
{
    public function __construct(
        public int $telegramUserId,
        public ?string $language = 'en'
    ) {
    }
}
