<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Expense\Domain\ExpenseDraft;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;

/**
 * A single step in the Add Expense flow.
 *
 * Each step renders one card state (AE-03, AE-06, AE-08, etc.) and
 * returns the updated draft. The flow handler composes steps and
 * manages draft persistence.
 */
interface AddExpenseStepInterface
{
    public function supports(ExpenseDraft $draft): bool;

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): ExpenseDraft;

    public function render(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null
    ): int;
}
