<?php

namespace App\Presentation\Telegram\Flow\FamilyInvitations;

use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;

interface FamilyInvitationsStepInterface
{
    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context
    ): void;
}
