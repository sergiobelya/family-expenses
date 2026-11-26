<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Expense\Application\ExpenseDraftService;
use App\Expense\Domain\ExpenseDraft;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Flow\Catalog\StoreEntryScreen;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ExpenseStoreCreationHandoff
{
    public function __construct(
        private ExpenseDraftService $draftService,
        private UserInteractionStateRepository $stateRepository,
        private StoreEntryScreen $storeEntryScreen,
        private TranslatorInterface $translator,
    ) {
    }

    public function start(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?string $prefillName = null
    ): void {
        $draft->setStep('store', null, 'store');
        $this->draftService->pauseDraft($draft, $context->user->getId());

        $returnContext = [
            'user_id' => (string) $draft->userId,
            'family_id' => (string) $draft->familyId,
            'telegram_user_id' => $draft->telegramUserId,
            'chat_id' => $draft->chatId,
            'draft_token' => $draft->idempotencyKey,
            'return_step' => 'amount',
            'expires_at' => $draft->expiresAt->format('Y-m-d H:i T'),
        ];
        $returnContext['pause_message'] = $this->pauseMessage($context, $draft);

        $this->stateRepository->clear($context->telegramUserId);
        $this->storeEntryScreen->startNameInput(
            $context,
            'create',
            prefill: $prefillName,
            expenseReturnContext: $returnContext
        );
    }

    public function pauseMessage(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): string {
        return $this->translator->trans('expense.add.draft.paused_for_creation', [
            '%{expires_at}' => $draft->expiresAt->format('Y-m-d H:i T'),
            '%{entity_name}' => $this->translator->trans('expense.add.store.entity_name', [], null, $context->language),
            '%{section_name}' => $this->translator->trans('expense.add.store.section_name', [], null, $context->language),
        ], null, $context->language);
    }
}
