<?php

namespace App\Presentation\Telegram\Context;

use App\Identity\Domain\Entity\User;

readonly class TelegramUserContext
{
    public function __construct(
        public User $user,
        public string $language,
        public int $telegramUserId,
        public int $chatId,
        public ?string $fromUsername = null,
        public ?string $fromFirstName = null
    ) {}
}
