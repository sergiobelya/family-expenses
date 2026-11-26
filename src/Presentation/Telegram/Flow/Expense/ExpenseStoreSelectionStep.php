<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Catalog\Application\Query\GetStoreQuery;
use App\Catalog\Application\Query\ListStoresQuery;
use App\Catalog\Application\Query\SearchStoresQuery;
use App\Catalog\Domain\Entity\Store;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Expense\Application\ExpenseDraftService;
use App\Expense\Domain\ExpenseDraft;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

final readonly class ExpenseStoreSelectionStep implements AddExpenseStepInterface
{
    private const int MAX_SEARCH_QUERY_LENGTH = 100;

    public function __construct(
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private ExpenseDraftCallbackData $callbackData,
        private ExpenseDraftCard $draftCard,
        private ExpenseDraftService $draftService,
        private ExpenseDraftScreen $draftScreen,
        private ExpenseStorePickerScreen $pickerScreen,
        private ExpenseStoreCreationHandoff $creationHandoff,
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(ExpenseDraft $draft): bool
    {
        return in_array($draft->currentStep, ['store', 'store_search'], true);
    }

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): ExpenseDraft {
        if ($update->callbackData !== null) {
            return $this->handleCallback($update, $context, $draft);
        }

        if ($draft->currentStep !== 'store_search' || $update->text === null) {
            $this->draftScreen->showUnexpectedInput($context, $draft);
            return $draft;
        }

        return $this->search($context, $draft, $update->text);
    }

    public function render(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null
    ): int {
        if ($draft->currentStep === 'store_search') {
            if ($draft->storeSearchQuery === null) {
                return $this->pickerScreen->renderSearchPrompt($context, $draft, $messageId, $errorKey);
            }

            [$results, $noticeKey] = $this->findMatches($context, $draft->storeSearchQuery);

            return $this->pickerScreen->renderSearchResults(
                $context,
                $draft,
                $messageId,
                $draft->storeSearchQuery,
                $results,
                $errorKey ?? $noticeKey
            );
        }

        return $this->pickerScreen->renderList(
            $context,
            $draft,
            $messageId,
            errorMessage: $errorKey === null ? null : $this->draftScreen->trans($errorKey, $context)
        );
    }

    private function handleCallback(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): ExpenseDraft {
        $callback = $this->callbackData->parse((string) $update->callbackData);
        if ($callback === null) {
            return $draft;
        }

        $action = $callback['action'];
        if (str_starts_with($action, CallbackActions::EXPENSE_STORE_SELECT_ACTION_PREFIX)) {
            $storeId = $this->callbackData->storeIdFromAction($action);
            $store = null;
            if ($storeId !== null) {
                /** @var Store|null $store */
                $store = $this->messageBusDispatcher->dispatchQuery(new GetStoreQuery(
                    $context->activeFamily->getId(),
                    $context->user->getId(),
                    $storeId
                ));
            }
            if ($store === null) {
                $storeName = $this->translator->trans('expense.add.store.selected', [], null, $context->language);
                $error = $this->translator->trans(
                    'expense.add.store.unavailable',
                    ['%{store_name}' => htmlspecialchars($storeName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
                    null,
                    $context->language
                );
                $this->answerCallback($update, $error);
                return $this->renderList($context, $draft, 0, $error);
            }

            $this->answerCallback($update);
            $draft->setStore($store->getId(), $store->getDefaultCategoryId());
            $draft->setStep('amount', 'amount');
            $this->draftCard->render($context, $draft, $draft->cardMessageId);
            return $draft;
        }

        if (str_starts_with($action, CallbackActions::EXPENSE_STORE_PAGE_ACTION_PREFIX)) {
            $pageValue = substr($action, strlen(CallbackActions::EXPENSE_STORE_PAGE_ACTION_PREFIX));
            if (ctype_digit($pageValue)) {
                $this->answerCallback($update);
                return $this->renderList($context, $draft, (int) $pageValue);
            }
        }

        if ($action === CallbackActions::EXPENSE_STORE_SEARCH_ACTION) {
            $this->answerCallback($update);
            $draft->setStoreSearchQuery(null);
            $draft->setStep('store_search', 'store_search');
            $this->renderPrompt($context, $draft);
            return $draft;
        }

        if ($action === CallbackActions::EXPENSE_STORE_SEARCH_BACK_ACTION) {
            $this->answerCallback($update);
            $draft->setStoreSearchQuery(null);
            $draft->setStep('store');
            return $this->renderList($context, $draft);
        }

        if ($action === CallbackActions::EXPENSE_STORE_CREATE_ACTION) {
            $this->answerCallback($update);
            $prefillName = $draft->currentStep === 'store_search' ? $draft->storeSearchQuery : null;
            $this->creationHandoff->start($context, $draft, $prefillName);
            return $draft;
        }

        $this->answerCallback($update);
        return $draft;
    }

    private function answerCallback(TelegramUpdate $update, ?string $text = null): void
    {
        if ($update->callbackQueryId !== null) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId, $text);
        }
    }

    private function search(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        string $rawQuery
    ): ExpenseDraft {
        $query = trim($rawQuery);
        if (mb_strlen($query) > self::MAX_SEARCH_QUERY_LENGTH) {
            $this->renderPrompt(
                $context,
                $draft,
                'expense.add.store.search.too_long',
                [
                    '%count%' => self::MAX_SEARCH_QUERY_LENGTH,
                    '%maxLength%' => self::MAX_SEARCH_QUERY_LENGTH,
                ]
            );
            return $draft;
        }
        if ($query === '' || preg_match('/[\p{L}\p{N}]/u', $query) !== 1) {
            $this->renderPrompt($context, $draft, 'expense.add.store.search.empty');
            return $draft;
        }

        $draft->setStoreSearchQuery($query);
        [$results, $noticeKey] = $this->findMatches($context, $query);
        if ($noticeKey === 'expense.add.store.search.unavailable') {
            $draft->setStoreSearchQuery(null);
            $draft->setStep('store');

            return $this->renderList(
                $context,
                $draft,
                errorMessage: $this->draftScreen->trans($noticeKey, $context)
            );
        }

        $messageId = $this->pickerScreen->renderSearchResults(
            $context,
            $draft,
            $draft->cardMessageId,
            $query,
            $results,
            $noticeKey
        );
        $draft->setCardMessage($messageId);
        $this->draftService->saveDraft($draft, $context->user->getId(), $context->activeFamily->getId());

        return $draft;
    }

    /** @return array{list<Store>, ?string} */
    private function findMatches(TelegramFamilyMemberContext $context, string $query): array
    {
        try {
            /** @var Store[] $stores */
            $stores = $this->messageBusDispatcher->dispatchQuery(new SearchStoresQuery(
                $context->activeFamily->getId(),
                $context->user->getId(),
                $query
            ));

            return [array_values($stores), null];
        } catch (Throwable) {
            try {
                $normalizedQuery = mb_strtolower($query);
                /** @var Store[] $stores */
                $stores = $this->messageBusDispatcher->dispatchQuery(new ListStoresQuery(
                    $context->activeFamily->getId(),
                    $context->user->getId()
                ));
                $matches = array_values(array_filter(
                    $stores,
                    static fn (Store $store): bool => str_contains(
                        mb_strtolower((string) $store->getName()),
                        $normalizedQuery
                    )
                ));
                return [$matches, 'expense.add.store.search.limited'];
            } catch (Throwable) {
                return [[], 'expense.add.store.search.unavailable'];
            }
        }
    }

    /** @param array<string, mixed> $errorParameters */
    private function renderPrompt(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?string $errorKey = null,
        array $errorParameters = []
    ): void {
        $messageId = $this->pickerScreen->renderSearchPrompt(
            $context,
            $draft,
            $draft->cardMessageId,
            $errorKey,
            $errorParameters
        );
        $draft->setCardMessage($messageId);
        $this->draftService->saveDraft($draft, $context->user->getId(), $context->activeFamily->getId());
    }

    private function renderList(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        int $page = 0,
        ?string $errorMessage = null
    ): ExpenseDraft {
        $messageId = $this->pickerScreen->renderList($context, $draft, $draft->cardMessageId, $page, $errorMessage);
        $draft->setCardMessage($messageId);
        $this->draftService->saveDraft($draft, $context->user->getId(), $context->activeFamily->getId());

        return $draft;
    }
}
