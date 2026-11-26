<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Expense\Application\ExpenseDraftService;
use App\Expense\Domain\ExpenseDraft;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use LogicException;

final readonly class ExpenseDraftCard
{
    public function __construct(
        private ExpenseDraftScreen $screen,
        private AddExpenseStepRouter $stepRouter,
        private ExpenseDraftService $draftService,
    ) {
    }

    public function reanchor(TelegramFamilyMemberContext $context, ExpenseDraft $draft): void
    {
        $previousMessageId = $draft->cardMessageId;
        $newMessageId = $this->render($context, $draft, null);

        if ($previousMessageId !== null && $previousMessageId !== $newMessageId) {
            $this->screen->closeMovedCard($context->chatId, $previousMessageId);
        }
    }

    public function render(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null
    ): int {
        $step = $this->stepRouter->resolve($draft);
        if ($step !== null) {
            $renderedMessageId = $step->render($context, $draft, $messageId, $errorKey);
        } elseif ($draft->currentStep === 'store') {
            $renderedMessageId = $this->screen->renderDraft($context, $draft, $messageId, $errorKey);
        } elseif ($draft->currentStep === 'amount') {
            $renderedMessageId = $this->screen->renderAmountPrompt($context, $draft, $messageId, $errorKey);
        } else {
            throw new LogicException('No renderer is registered for the current expense draft step.');
        }

        $draft->setCardMessage($renderedMessageId);
        $this->draftService->saveDraft($draft, $context->user->getId(), $context->activeFamily->getId());

        return $renderedMessageId;
    }

    public function showSaveUnknown(TelegramFamilyMemberContext $context, ExpenseDraft $draft): void
    {
        $messageId = $this->screen->showSaveUnknown($context, $draft);
        if ($draft->cardMessageId !== $messageId) {
            $draft->setCardMessage($messageId);
            $this->draftService->saveDraft($draft, $context->user->getId(), $context->activeFamily->getId());
        }
    }
}
