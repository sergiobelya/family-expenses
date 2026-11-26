<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Expense\Application\ExpenseDraftService;
use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\ExpenseDraftStatus;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use DateTimeImmutable;

final readonly class ExpenseDraftEntry
{
    public function __construct(
        private ExpenseDraftService $draftService,
        private ExpenseDraftScreen $screen,
        private ExpenseDraftCard $draftCard,
    ) {
    }

    public function startOrResume(TelegramFamilyMemberContext $context, ExpenseEntryIntent $intent): void
    {
        $this->handleEntry($context, $intent, $this->findDraft($context));
    }

    public function findDraft(TelegramFamilyMemberContext $context): ?ExpenseDraft
    {
        return $this->draftService->findDraft(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId
        );
    }

    public function startDraft(TelegramFamilyMemberContext $context, ?int $messageId = null): void
    {
        $draft = $this->draftService->createDraft(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId,
            'store',
            new DateTimeImmutable('today', $context->activeFamily->getTimezone()->toDateTimeZone())
        );
        $this->draftCard->render($context, $draft, $messageId);
    }

    private function handleEntry(
        TelegramFamilyMemberContext $context,
        ExpenseEntryIntent $intent,
        ?ExpenseDraft $draft
    ): void {
        if ($draft !== null && $draft->isExpired()) {
            $this->draftService->removeDraft($context->user->getId(), $context->activeFamily->getId());
            $draft = null;

            if ($intent === ExpenseEntryIntent::CONTINUE) {
                $this->screen->showExpired($context, null, null, null);
                return;
            }
        }

        if ($intent === ExpenseEntryIntent::CONTINUE && $draft !== null && $draft->status->isTerminal()) {
            $this->screen->showExpired($context, null, null, null, 'expense.add.callback.stale');
            return;
        }

        if ($draft?->status === ExpenseDraftStatus::SAVING) {
            $this->screen->showSaveInProgress($context);
            return;
        }

        if ($draft?->status === ExpenseDraftStatus::SAVE_UNKNOWN) {
            $this->draftCard->showSaveUnknown($context, $draft);
            return;
        }

        if ($draft !== null && in_array($draft->status, [ExpenseDraftStatus::ACTIVE, ExpenseDraftStatus::PAUSED], true)) {
            if ($draft->status === ExpenseDraftStatus::PAUSED) {
                $this->draftService->resumeDraft($draft, $context->user->getId());
            }
            $this->draftCard->reanchor($context, $draft);
            return;
        }

        if ($intent === ExpenseEntryIntent::CONTINUE && $draft === null) {
            $this->screen->showExpired($context, null, null, null);
            return;
        }

        $this->startDraft($context);
    }
}
