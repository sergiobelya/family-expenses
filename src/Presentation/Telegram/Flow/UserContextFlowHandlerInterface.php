<?php

namespace App\Presentation\Telegram\Flow;

use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;

interface UserContextFlowHandlerInterface extends FlowHandlerInterface
{
    public function handle(TelegramUpdate $update, TelegramUserContext $context): void;
}
