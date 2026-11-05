<?php

namespace App\Presentation\Telegram\Context;

use App\Family\Domain\Entity\Family;
use App\Identity\Domain\Entity\User;

final readonly class TelegramUserContext
{
    public function __construct(
        public User $user,
        public ?Family $activeFamily,
        public string $language,
        public int $telegramUserId,
        public int $chatId,
        public ?string $fromUsername = null,
        public ?string $fromFirstName = null
    ) {}

    public function hasActiveFamily(): bool
    {
        return $this->activeFamily !== null;
    }
}
