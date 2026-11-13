<?php

namespace App\Presentation\Telegram\Flow;

use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;

interface FamilyContextFlowHandlerInterface extends FlowHandlerInterface
{
    public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context): void;
}
