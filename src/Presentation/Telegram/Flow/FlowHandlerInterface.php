<?php

namespace App\Presentation\Telegram\Flow;

use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;

interface FlowHandlerInterface
{
    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool;

    public function handle(TelegramUpdate $update, TelegramUserContext $context): void;
}
