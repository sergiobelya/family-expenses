<?php

namespace App\Presentation\Telegram\Flow\FamilySettings;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;

interface FamilySettingsStepInterface
{
    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        UserInteractionState $state
    ): ?UserInteractionState;
}
