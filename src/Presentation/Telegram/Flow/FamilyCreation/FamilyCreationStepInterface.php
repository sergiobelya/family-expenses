<?php

namespace App\Presentation\Telegram\Flow\FamilyCreation;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;

interface FamilyCreationStepInterface
{
    public function handle(
        TelegramUpdate $update,
        TelegramUserContext $context,
        UserInteractionState $state
    ): ?UserInteractionState;
}
