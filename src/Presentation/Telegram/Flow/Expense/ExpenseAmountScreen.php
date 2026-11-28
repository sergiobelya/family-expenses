<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Application\CurrencyCatalog;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Expense\Application\Query\GetExpenseStoreQuery;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\ExpenseDraft;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ExpenseAmountScreen
{
    private const int CURRENCIES_PER_PAGE = 12;
    private const int CURRENCIES_PER_ROW = 4;
    private const array QUICK_CURRENCIES = ['PLN', 'UAH', 'EUR', 'USD'];

    public function __construct(
        private ExpenseCardRenderer $cardRenderer,
        private ExpenseDraftCallbackData $callbackData,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private CurrencyCatalog $currencyCatalog,
        private MoneyAmountFormatter $amountFormatter,
        private AmountParser $amountParser,
        private TranslatorInterface $translator,
    ) {
    }

    public function renderAmount(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null,
        ?string $errorText = null,
        ?string $notice = null,
    ): int {
        $currency = $draft->currency ?? (string) $context->activeFamily->getDefaultExpenseCurrency();
        $errorText ??= $errorKey === null ? null : $this->trans($errorKey, $context);
        if (
            $errorText === null
            && $draft->amount !== null
            && $this->amountParser->exceedsCurrencyPrecision($draft->amount, $currency)
        ) {
            $errorText = $this->trans('expense.add.currency.precision_conflict', $context, [
                '%{currency}' => $currency,
                '%{amount}' => $this->amountFormatter->formatNumber($draft->amount, $currency, $context->language),
            ]);
        }

        if ($draft->amount === null) {
            $prompt = $this->trans('expense.amount.prompt', $context, [
                '%{currency}' => $currency,
                '%{example_amount}' => '125.50',
            ]);
        } else {
            $prompt = $this->trans('expense.add.amount.edit_prompt', $context, [
                '%{amount}' => $this->amountFormatter->formatNumber($draft->amount, $currency, $context->language)
                    . ' ' . $currency,
            ]);
            if ($notice !== null) {
                $prompt = $notice . "\n" . $prompt;
            }
        }

        $buttons = [];
        $currencies = self::QUICK_CURRENCIES;
        if (!in_array($currency, $currencies, true)) {
            $currencies[] = $currency;
        }
        foreach ($currencies as $index => $code) {
            $buttons[] = [
                'text' => ($code === $currency ? '✓ ' : '') . $code,
                'callback' => $this->callbackData->forDraft(
                    $draft,
                    CallbackActions::EXPENSE_AMOUNT_CURRENCY_ACTION_PREFIX . $code
                ),
                'row' => intdiv($index, self::CURRENCIES_PER_ROW),
            ];
        }

        $moreRow = (int) ceil(count($currencies) / self::CURRENCIES_PER_ROW);
        $buttons[] = $this->button(
            $draft,
            $this->trans('expense.add.currency.more', $context),
            CallbackActions::EXPENSE_AMOUNT_MORE_CURRENCIES_ACTION,
            $moreRow
        );

        $precisionConflict = $draft->amount !== null
            && $this->amountParser->exceedsCurrencyPrecision($draft->amount, $currency);
        if ($draft->amount !== null && !$precisionConflict) {
            $buttons[] = $this->button(
                $draft,
                $this->trans('expense.add.amount.continue', $context),
                CallbackActions::EXPENSE_AMOUNT_CONTINUE_ACTION,
                $moreRow + 1
            );
        }

        $backText = $draft->returnStep === 'review'
            ? $this->trans('expense.add.btn.back_to_review', $context)
            : $this->trans('common.back', $context);
        $buttons[] = $this->button(
            $draft,
            $backText,
            $draft->returnStep === 'review'
                ? CallbackActions::EXPENSE_AMOUNT_BACK_REVIEW_ACTION
                : CallbackActions::EXPENSE_AMOUNT_BACK_ACTION,
            $moreRow + 2
        );

        return $this->renderCard($context, $draft, $messageId, $prompt, $errorText, $buttons, $precisionConflict);
    }

    public function renderCurrencyList(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null,
    ): int {
        $currencies = $this->currencyCatalog->all($context->language);
        $totalPages = max(1, (int) ceil(count($currencies) / self::CURRENCIES_PER_PAGE));
        $page = min(max($draft->currencyPage, 0), $totalPages - 1);
        $pageCurrencies = array_slice($currencies, $page * self::CURRENCIES_PER_PAGE, self::CURRENCIES_PER_PAGE);
        $buttons = [];

        foreach ($pageCurrencies as $index => $currency) {
            $code = $currency->code;
            $buttons[] = [
                'text' => $code,
                'callback' => $this->currencyCallback($draft, $code),
                'row' => intdiv($index, self::CURRENCIES_PER_ROW),
            ];
        }

        $navigationRow = (int) ceil(count($pageCurrencies) / self::CURRENCIES_PER_ROW);
        if ($page > 0) {
            $buttons[] = $this->button(
                $draft,
                $this->trans('expense.add.pagination.previous', $context),
                CallbackActions::EXPENSE_CURRENCY_PAGE_ACTION_PREFIX . ($page - 1),
                $navigationRow
            );
        }
        if ($page + 1 < $totalPages) {
            $buttons[] = $this->button(
                $draft,
                $this->trans('expense.add.pagination.next', $context),
                CallbackActions::EXPENSE_CURRENCY_PAGE_ACTION_PREFIX . ($page + 1),
                $navigationRow
            );
        }

        $actionsRow = $navigationRow + 1;
        $buttons[] = $this->button(
            $draft,
            $this->trans('expense.add.currency.search.button', $context),
            CallbackActions::EXPENSE_CURRENCY_SEARCH_ACTION,
            $actionsRow
        );
        $buttons[] = $this->button(
            $draft,
            $this->trans('common.back', $context),
            CallbackActions::EXPENSE_CURRENCY_BACK_ACTION,
            $actionsRow
        );

        $prompt = $this->trans('expense.add.currency.more_prompt', $context);
        if ($totalPages > 1) {
            $prompt .= "\n" . $this->trans('expense.add.pagination.page', $context, [
                '%{current}' => (string) ($page + 1),
                '%{total}' => (string) $totalPages,
            ]);
        }

        return $this->renderCard(
            $context,
            $draft,
            $messageId,
            $prompt,
            $errorKey === null ? null : $this->trans($errorKey, $context),
            $buttons
        );
    }

    public function renderCurrencySearch(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null,
    ): int {
        $query = $draft->currencySearchQuery;
        $buttons = [];
        if ($query === null) {
            $prompt = $this->trans('expense.add.currency.search.prompt', $context);
        } else {
            $currencies = $this->currencyCatalog->search($query, $context->language);
            if ($currencies === []) {
                $prompt = $this->trans('expense.add.currency.search.none', $context, [
                    '%{query}' => $this->escape($query),
                ]);
            } else {
                $prompt = $this->trans('expense.add.currency.search.results', $context, [
                    '%{query}' => $this->escape($query),
                ]);
                foreach ($currencies as $index => $currency) {
                    $buttons[] = [
                        'text' => $currency->code . ' — ' . $currency->localizedName,
                        'callback' => $this->currencyCallback($draft, $currency->code),
                        'row' => $index,
                    ];
                }
            }
        }

        $actionsRow = count($buttons);
        $buttons[] = $this->button(
            $draft,
            $this->trans('expense.add.currency.search.list', $context),
            CallbackActions::EXPENSE_CURRENCY_SEARCH_BACK_ACTION,
            $actionsRow
        );
        $buttons[] = $this->button(
            $draft,
            $this->trans('common.back', $context),
            CallbackActions::EXPENSE_CURRENCY_BACK_ACTION,
            $actionsRow + 1
        );

        return $this->renderCard(
            $context,
            $draft,
            $messageId,
            $prompt,
            $errorKey === null ? null : $this->trans($errorKey, $context),
            $buttons
        );
    }

    /** @param list<array{text: string, callback: string, row: int}> $buttons */
    private function renderCard(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        string $prompt,
        ?string $error,
        array $buttons,
        bool $preserveAmountPrecision = false,
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
            $error,
            $context->language,
            $preserveAmountPrecision
        );

        return $this->cardRenderer->render($context->chatId, $messageId, $text, $buttons);
    }

    /**
     * @return array{text: string, callback: string, row: int}
     */
    private function button(ExpenseDraft $draft, string $text, string $action, int $row): array
    {
        return [
            'text' => $text,
            'callback' => $this->callbackData->forDraft($draft, $action),
            'row' => $row,
        ];
    }

    private function currencyCallback(ExpenseDraft $draft, string $code): string
    {
        return $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_CURRENCY_SELECT_ACTION_PREFIX . $code);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function trans(string $key, TelegramFamilyMemberContext $context, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, null, $context->language);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
