<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\FamilyContextFlowHandlerInterface;

/**
 * Handles the Add Expense Telegram flow (AE-00 through AE-18).
 *
 * This is the entry point for the expense creation wizard. It manages
 * draft lifecycle (create, resume, pause, discard) and delegates to
 * step handlers for each card state.
 *
 * Subtask 1 creates the infrastructure skeleton. Subtasks 2–7
 * implement the actual step handlers.
 */
readonly class AddExpenseFlowHandler implements FamilyContextFlowHandlerInterface
{
    public function __construct(
        // @phpstan-ignore property.onlyWritten
        private ExpenseFlowUtils $flowUtils,
        // @phpstan-ignore property.onlyWritten
        private ExpenseCardRenderer $cardRenderer,
        private TelegramApiClientInterface $telegramClient,
        // @phpstan-ignore property.onlyWritten
        private ExpenseDraftRepositoryInterface $draftRepository,
    ) {
    }

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        // Callback data starting with 'exp:'
        if (str_starts_with($update->callbackData ?? '', CallbackActions::EXPENSE_PREFIX)) {
            return true;
        }

        // Text: "Add expense" menu button or /add command
        if ($update->text !== null) {
            if ($update->text === '/add') {
                return true;
            }
            // The "Add expense" and "Continue creating expense" menu buttons
            // are handled by matching the translation keys in the handler
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        $family = $context->activeFamily;
        $userId = $context->user->getId();
        $familyId = $family->getId();

        // TODO (Subtask 2–7): Route to the appropriate step handler
        // based on callback data or text input.
        //
        // The routing logic will:
        // 1. Check for existing draft (AE-02 resume)
        // 2. Create new draft if needed (AE-00 → AE-03)
        // 3. Route callback data to the matching step
        // 4. Handle text input for the current awaiting-input step
        // 5. Handle stale callbacks (AE-18) and expired drafts (AE-17)
        //
        // For now, this is a placeholder that will be filled in
        // by subsequent subtasks.
    }
}

