<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\ExpenseDraftStatus;
use App\Expense\Domain\Repository\ExpenseDraftRepositoryInterface;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\FamilyContextFlowHandlerInterface;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;

/**
 * Handles the Add Expense Telegram flow (AE-00 through AE-18).
 *
 * This is the entry point for the expense creation wizard. It routes Telegram
 * updates and delegates draft entry and card rendering to focused services.
 */
final readonly class AddExpenseFlowHandler implements FamilyContextFlowHandlerInterface
{
    public function __construct(
        private ExpenseDraftEntry $draftEntry,
        private ExpenseDraftCard $draftCard,
        private ExpenseDraftScreen $screen,
        private ExpenseDraftCallbackData $callbackData,
        private AddExpenseStepRouter $stepRouter,
        private TelegramApiClientInterface $telegramClient,
        private ExpenseDraftRepositoryInterface $draftRepository,
        private MainMenuKeyboard $mainMenuKeyboard,
    ) {
    }

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if (str_starts_with($update->callbackData ?? '', CallbackActions::EXPENSE_PREFIX)) {
            return true;
        }

        if ($context instanceof TelegramFamilyMemberContext && $this->entryIntent($update->text, $context) !== null) {
            return true;
        }

        if (!$context instanceof TelegramFamilyMemberContext || !$update->isMessage()) {
            return false;
        }

        if (
            $update->text !== null
            && (
                str_starts_with(trim($update->text), '/')
                || $this->mainMenuKeyboard->isMenuButton($update->text)
            )
        ) {
            return false;
        }

        $draft = $this->draftRepository->find($context->user->getId(), $context->activeFamily->getId());

        return $draft !== null
            && in_array(
                $draft->status,
                [ExpenseDraftStatus::ACTIVE, ExpenseDraftStatus::SAVING, ExpenseDraftStatus::SAVE_UNKNOWN],
                true
            )
            && !$draft->isExpired();
    }

    public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->handleCallback($update, $context, $this->draftEntry->findDraft($context));
            return;
        }

        $intent = $this->entryIntent($update->text, $context);
        if ($intent !== null) {
            $this->draftEntry->startOrResume($context, $intent);
            return;
        }

        $draft = $this->draftEntry->findDraft($context);
        if ($draft === null) {
            return;
        }

        if ($draft->isExpired()) {
            $this->draftRepository->remove($context->user->getId(), $context->activeFamily->getId());
            $this->screen->showExpired($context, null, null, null);
            return;
        }

        if ($draft->status === ExpenseDraftStatus::SAVING) {
            $this->screen->showSaveInProgress($context);
            return;
        }

        if ($draft->status === ExpenseDraftStatus::SAVE_UNKNOWN) {
            $this->draftCard->showSaveUnknown($context, $draft);
            return;
        }

        if ($draft->status !== ExpenseDraftStatus::ACTIVE) {
            return;
        }

        if ($draft->awaitingInput !== null && $update->text === null) {
            $this->draftCard->render($context, $draft, $draft->cardMessageId, 'expense.add.input.not_text');
            return;
        }

        $step = $this->stepRouter->resolve($draft);
        if ($step !== null) {
            $this->draftRepository->save($step->handle($update, $context, $draft));
            return;
        }

        $this->screen->showUnexpectedInput($context, $draft);
    }

    private function handleCallback(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        ?ExpenseDraft $draft
    ): void {
        $callbackQueryId = $update->callbackQueryId;
        if ($callbackQueryId === null) {
            return;
        }

        $callback = $this->callbackData->parse($update->callbackData ?? '');

        if ($draft !== null && $draft->isExpired()) {
            $this->draftRepository->remove($context->user->getId(), $context->activeFamily->getId());
            $draft = null;
        }

        $terminalDraft = $draft !== null && $draft->status->isTerminal();
        if ($callback !== null && ($draft === null || $terminalDraft)) {
            $messageKey = $terminalDraft
                ? 'expense.add.callback.stale'
                : 'expense.add.expired.message';

            if ($callback['action'] === CallbackActions::EXPENSE_EXPIRED_CLOSE_ACTION) {
                $this->telegramClient->answerCallbackQuery($callbackQueryId);
                $this->screen->closeExpiredCard($context, $update->callbackMessageId, $messageKey);
                return;
            }
            if ($callback['action'] === CallbackActions::EXPENSE_EXPIRED_RESTART_ACTION) {
                $this->telegramClient->answerCallbackQuery($callbackQueryId);
                $this->draftEntry->startDraft($context, $update->callbackMessageId);
                return;
            }

            $this->telegramClient->answerCallbackQuery($callbackQueryId);
            $this->screen->showExpired(
                $context,
                $update->callbackMessageId,
                $callback['token'],
                $callback['revision'],
                $messageKey
            );
            return;
        }

        if ($draft === null || $callback === null) {
            $this->answerStaleCallback($update, $context, $draft);
            return;
        }

        if ($draft->status === ExpenseDraftStatus::SAVING) {
            $this->telegramClient->answerCallbackQuery(
                $callbackQueryId,
                $this->screen->trans('expense.add.save.in_progress', $context)
            );
            return;
        }

        if ($draft->status === ExpenseDraftStatus::SAVE_UNKNOWN) {
            $this->telegramClient->answerCallbackQuery($callbackQueryId);
            $this->draftCard->showSaveUnknown($context, $draft);
            return;
        }

        if (
            !$this->callbackData->matchesDraft($callback, $draft, $update)
            || $draft->status !== ExpenseDraftStatus::ACTIVE
        ) {
            $this->answerStaleCallback($update, $context, $draft);
            return;
        }

        if ($callback['action'] === CallbackActions::EXPENSE_SHOW_CARD_ACTION) {
            $this->telegramClient->answerCallbackQuery($callbackQueryId);
            $this->draftCard->reanchor($context, $draft);
            return;
        }

        $step = $this->stepRouter->resolve($draft);
        if ($step !== null) {
            $this->telegramClient->answerCallbackQuery($callbackQueryId);
            $this->draftRepository->save($step->handle($update, $context, $draft));
            return;
        }

        $this->answerStaleCallback($update, $context, $draft);
    }

    private function answerStaleCallback(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        ?ExpenseDraft $draft
    ): void {
        if ($update->callbackQueryId !== null) {
            $this->telegramClient->answerCallbackQuery(
                $update->callbackQueryId,
                $this->screen->trans('expense.add.callback.stale', $context)
            );
        }

        if ($draft !== null && !$draft->isExpired()) {
            if ($draft->status === ExpenseDraftStatus::ACTIVE) {
                $this->draftCard->reanchor($context, $draft);
                return;
            }

            if ($draft->status === ExpenseDraftStatus::PAUSED) {
                return;
            }
        }

        $this->screen->showExpired($context, $update->callbackMessageId, null, null);
    }

    private function entryIntent(?string $text, TelegramUserContext $context): ?ExpenseEntryIntent
    {
        if ($text === $this->screen->trans('main_menu.continue_expense', $context)) {
            return ExpenseEntryIntent::CONTINUE;
        }

        if ($text === '/add' || $text === $this->screen->trans('main_menu.add_expense', $context)) {
            return ExpenseEntryIntent::ADD;
        }

        return null;
    }

}
