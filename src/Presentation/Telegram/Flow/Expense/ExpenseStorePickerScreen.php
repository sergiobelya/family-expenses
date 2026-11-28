<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Catalog\Application\Query\ListStoresQuery;
use App\Catalog\Domain\Entity\Store;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Expense\Application\Query\GetExpenseStoreQuery;
use App\Expense\Application\Query\GetStoreIdsByLastPurchaseQuery;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\ExpenseDraft;
use App\Family\Application\Query\GetActiveFamilyCountQuery;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ExpenseStorePickerScreen
{
    public const int PAGE_SIZE = 8;
    private const int SEARCH_RESULT_LIMIT = 5;

    public function __construct(
        private ExpenseCardRenderer $cardRenderer,
        private ExpenseDraftCallbackData $callbackData,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private TranslatorInterface $translator,
    ) {
    }

    public function renderList(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        int $page = 0,
        ?string $errorMessage = null
    ): int {
        $stores = $this->storesByLastPurchase($context);
        $totalPages = max(1, (int) ceil(count($stores) / self::PAGE_SIZE));
        $page = min(max($page, 0), $totalPages - 1);
        $pageStores = array_slice($stores, $page * self::PAGE_SIZE, self::PAGE_SIZE);
        $buttons = [];

        if ($stores === []) {
            $prompt = $this->trans('expense.add.store.no_stores', $context);
            $buttons[] = $this->button(
                $this->trans('expense.add.store.add_first', $context),
                $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_STORE_CREATE_ACTION)
            );
        } else {
            $prompt = $this->trans('expense.add.store.prompt', $context);
            if ($totalPages > 1) {
                $prompt .= "\n" . $this->trans('expense.add.pagination.page', $context, [
                    '%{current}' => (string) ($page + 1),
                    '%{total}' => (string) $totalPages,
                ]);
            }

            foreach ($pageStores as $index => $store) {
                $buttons[] = [
                    'text' => $this->truncateButton((string) $store->getName()),
                    'callback' => $this->callbackData->forStore($draft, $store->getId()),
                    'row' => intdiv($index, 2),
                ];
            }

            $navigationRow = (int) ceil(count($pageStores) / 2);
            if ($page > 0) {
                $buttons[] = $this->button(
                    $this->trans('expense.add.pagination.previous', $context),
                    $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_STORE_PAGE_ACTION_PREFIX . ($page - 1)),
                    $navigationRow
                );
            }
            if ($page + 1 < $totalPages) {
                $buttons[] = $this->button(
                    $this->trans('expense.add.pagination.next', $context),
                    $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_STORE_PAGE_ACTION_PREFIX . ($page + 1)),
                    $navigationRow
                );
            }

            $actionsRow = $navigationRow + 1;
            $buttons[] = $this->button(
                $this->trans('catalog.search', $context),
                $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_STORE_SEARCH_ACTION),
                $actionsRow
            );
            $buttons[] = $this->button(
                $this->trans('catalog.add', $context),
                $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_STORE_CREATE_ACTION),
                $actionsRow
            );
        }

        if ($draft->storeId === null && $this->hasMultipleFamilies($context)) {
            $buttons[] = $this->button(
                $this->trans('expense.add.family.change.btn', $context),
                CallbackActions::FAMILY_SELECTOR,
                (int) ($buttons[array_key_last($buttons)]['row'] ?? 0) + 1
            );
        }

        return $this->renderCard($context, $draft, $messageId, $prompt, $errorMessage, $buttons);
    }

    /** @param array<string, mixed> $errorParameters */
    public function renderSearchPrompt(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null,
        array $errorParameters = []
    ): int {
        $buttons = [$this->button(
            $this->trans('common.back', $context),
            $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_STORE_SEARCH_BACK_ACTION)
        )];

        return $this->renderCard(
            $context,
            $draft,
            $messageId,
            $this->trans('expense.add.store.search.prompt', $context),
            $errorKey === null ? null : $this->trans($errorKey, $context, $errorParameters),
            $buttons
        );
    }

    /**
     * @param Store[] $stores
     */
    public function renderSearchResults(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        string $query,
        array $stores,
        ?string $noticeKey = null
    ): int {
        $buttons = [];
        foreach (array_slice($stores, 0, self::SEARCH_RESULT_LIMIT) as $index => $store) {
            $buttons[] = [
                'text' => $this->truncateButton((string) $store->getName()),
                'callback' => $this->callbackData->forStore($draft, $store->getId()),
                'row' => $index,
            ];
        }

        $buttons[] = $this->button(
            $this->trans('expense.add.store.search.create', $context, ['%{query}' => $this->escape($query)]),
            $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_STORE_CREATE_ACTION),
            count($buttons)
        );
        $buttons[] = $this->button(
            $this->trans('common.back', $context),
            $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_STORE_SEARCH_BACK_ACTION),
            count($buttons)
        );

        $errorMessages = [];
        if ($noticeKey !== null) {
            $errorMessages[] = $this->trans($noticeKey, $context, ['%{query}' => $this->escape($query)]);
        }
        if ($stores === []) {
            $errorMessages[] = $this->trans(
                'expense.add.store.search.none',
                $context,
                ['%{query}' => $this->escape($query)]
            );
        }
        $error = $errorMessages === [] ? null : implode("\n", $errorMessages);

        return $this->renderCard(
            $context,
            $draft,
            $messageId,
            $this->trans('expense.add.store.search.results', $context, ['%{query}' => $this->escape($query)]),
            $error,
            $buttons
        );
    }

    /** @return Store[] */
    private function storesByLastPurchase(TelegramFamilyMemberContext $context): array
    {
        /** @var list<StoreId> $storeIds */
        $storeIds = $this->messageBusDispatcher->dispatchQuery(new GetStoreIdsByLastPurchaseQuery(
            $context->activeFamily->getId(),
            $context->user->getId()
        ));

        /** @var Store[] $stores */
        $stores = $this->messageBusDispatcher->dispatchQuery(new ListStoresQuery(
            $context->activeFamily->getId(),
            $context->user->getId()
        ));

        return $this->orderByStoreIds($stores, $storeIds);
    }

    /**
     * @param Store[] $stores
     * @param list<StoreId> $storeIds
     * @return Store[]
     */
    private function orderByStoreIds(array $stores, array $storeIds): array
    {
        $rank = [];
        foreach ($storeIds as $index => $storeId) {
            $rank[(string) $storeId] = $index;
        }

        usort($stores, static function (Store $left, Store $right) use ($rank): int {
            $leftId = (string) $left->getId();
            $rightId = (string) $right->getId();
            $leftRank = $rank[$leftId] ?? PHP_INT_MAX;
            $rightRank = $rank[$rightId] ?? PHP_INT_MAX;
            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            $nameComparison = strcmp(mb_strtolower((string) $left->getName()), mb_strtolower((string) $right->getName()));

            return $nameComparison !== 0 ? $nameComparison : strcmp($leftId, $rightId);
        });

        return $stores;
    }

    private function hasMultipleFamilies(TelegramFamilyMemberContext $context): bool
    {
        return $this->messageBusDispatcher->dispatchQuery(new GetActiveFamilyCountQuery($context->user->getId())) > 1;
    }

    /**
     * @param list<array{text: string, callback: string, row?: int}> $buttons
     */
    private function renderCard(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        string $prompt,
        ?string $error,
        array $buttons
    ): int {
        $store = null;
        if ($draft->storeId !== null) {
            /** @var ExpenseStore|null $store */
            $store = $this->messageBusDispatcher->dispatchQuery(new GetExpenseStoreQuery(
                $context->activeFamily->getId(),
                $context->user->getId(),
                $draft->storeId
            ));
        }
        $text = $this->cardRenderer->buildCardText(
            $context->activeFamily,
            $draft,
            $store,
            null,
            $prompt,
            $error
        );

        return $this->cardRenderer->render($context->chatId, $messageId, $text, $buttons);
    }

    /** @return array{text: string, callback: string, row?: int} */
    private function button(string $text, string $callback, ?int $row = null): array
    {
        return $row === null
            ? ['text' => $text, 'callback' => $callback]
            : ['text' => $text, 'callback' => $callback, 'row' => $row];
    }

    private function truncateButton(string $name): string
    {
        return mb_strlen($name) > 60 ? mb_substr($name, 0, 57) . '…' : $name;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string|int, mixed> $parameters */
    private function trans(string $key, TelegramFamilyMemberContext $context, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, null, $context->language);
    }
}
