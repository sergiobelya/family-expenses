<?php

namespace App\Presentation\Telegram\Context;

use App\Family\Domain\Entity\Family;

final readonly class TelegramFamilyMemberContext extends TelegramUserContext
{
    public function __construct(
        TelegramUserContext $userContext,
        public Family $activeFamily
    ) {
        parent::__construct(
            user: $userContext->user,
            language: $userContext->language,
            telegramUserId: $userContext->telegramUserId,
            chatId: $userContext->chatId,
            fromUsername: $userContext->fromUsername,
            fromFirstName: $userContext->fromFirstName
        );
    }
}
