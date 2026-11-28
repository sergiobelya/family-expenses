<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Catalog\Application\Query\GetStoreQuery;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Expense\Application\ExpenseDraftService;
use App\Expense\Domain\ExpenseDraftStatus;
use App\Catalog\Domain\Entity\Store;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ExpenseStoreReturn
{
    public function __construct(
        private ExpenseDraftService $draftService,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private ExpenseDraftCard $draftCard,
        private ExpenseDraftScreen $draftScreen,
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator,
    ) {
    }

    /** @param array<string, mixed>|null $returnContext */
    public function handle(
        TelegramFamilyMemberContext $context,
        ?array $returnContext,
        ?string $storeIdValue
    ): void {
        if (!$this->returnContextMatches($context, $returnContext)) {
            $this->showStaleMessage($context);
            return;
        }

        $draft = $this->draftService->findDraft(
            $context->user->getId(),
            $context->activeFamily->getId(),
            $context->telegramUserId,
            $context->chatId
        );
        if (
            $draft === null
            || $draft->status !== ExpenseDraftStatus::PAUSED
            || $draft->currentStep !== 'store'
            || $draft->returnStep !== 'store'
            || !hash_equals($draft->idempotencyKey, (string) ($returnContext['draft_token'] ?? ''))
            || $draft->telegramUserId !== $context->telegramUserId
            || $draft->chatId !== $context->chatId
        ) {
            $this->showStaleMessage($context);
            return;
        }

        if ($draft->isExpired()) {
            $this->draftService->removeDraft($context->user->getId(), $context->activeFamily->getId());
            $this->draftScreen->showExpired($context, null, null, null);
            return;
        }

        if ($storeIdValue === null || !Uuid::isValid($storeIdValue)) {
            $this->showStaleMessage($context);
            return;
        }

        /** @var Store|null $store */
        $store = $this->messageBusDispatcher->dispatchQuery(new GetStoreQuery(
            $context->activeFamily->getId(),
            $context->user->getId(),
            StoreId::fromString($storeIdValue)
        ));
        if ($store === null) {
            $this->draftService->resumeDraft($draft, $context->user->getId());
            $draft->setStep('store');
            $this->draftCard->render($context, $draft, $draft->cardMessageId, 'expense.add.store.unavailable_generic');
            return;
        }

        $this->draftService->resumeDraft($draft, $context->user->getId());
        $draft->setStore($store->getId(), $store->getDefaultCategoryId());
        $draft->setStep((string) ($returnContext['return_step'] ?? 'amount'), 'amount');
        $this->draftCard->reanchor($context, $draft);
    }

    /** @param array<string, mixed>|null $returnContext */
    private function returnContextMatches(TelegramFamilyMemberContext $context, ?array $returnContext): bool
    {
        return $returnContext !== null
            && ($returnContext['user_id'] ?? null) === (string) $context->user->getId()
            && ($returnContext['family_id'] ?? null) === (string) $context->activeFamily->getId()
            && ($returnContext['telegram_user_id'] ?? null) === $context->telegramUserId
            && ($returnContext['chat_id'] ?? null) === $context->chatId
            && is_string($returnContext['draft_token'] ?? null)
            && ($returnContext['return_step'] ?? null) === 'amount';
    }

    private function showStaleMessage(TelegramFamilyMemberContext $context): void
    {
        $this->telegramClient->sendMessage(
            $context->chatId,
            $this->translator->trans('expense.add.callback.stale', [], null, $context->language)
        );
    }
}
