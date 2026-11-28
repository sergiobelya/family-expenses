<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Expense\Domain\ExpenseDraft;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ExpenseCurrencySelectionFlow
{
    public function __construct(
        private ExpenseAmountScreen $screen,
        private MoneyAmountFormatter $amountFormatter,
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(ExpenseDraft $draft): bool
    {
        return in_array($draft->currentStep, ['currency_list', 'currency_search'], true);
    }

    public function render(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null
    ): int {
        return match ($draft->currentStep) {
            'currency_list' => $this->screen->renderCurrencyList($context, $draft, $messageId, $errorKey),
            'currency_search' => $this->screen->renderCurrencySearch($context, $draft, $messageId, $errorKey),
            default => throw new \LogicException('The draft is not in a currency selection step.'),
        };
    }

    public function handleTextInput(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?string $text
    ): bool {
        if (!in_array($draft->currentStep, ['currency_list', 'currency_search'], true) || $text === null) {
            return false;
        }

        if ($draft->currentStep === 'currency_list') {
            $draft->setStep('currency_search', 'currency_search', $draft->returnStep);
        }

        $query = trim($text);
        $draft->setCurrencySearchQuery($query === '' ? null : $query);
        $errorKey = $query === '' ? 'expense.add.currency.search.empty' : null;
        $this->renderCurrencySearch($context, $draft, $errorKey);

        return true;
    }

    public function handleCallback(
        string $callbackQueryId,
        string $action,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): bool {
        if ($this->handleCurrencySelectionCallback($callbackQueryId, $action, $context, $draft)) {
            return true;
        }

        if ($this->handleCurrencyListCallback($callbackQueryId, $action, $context, $draft)) {
            return true;
        }

        return $this->handleCurrencyBackCallback($callbackQueryId, $action, $context, $draft);
    }

    private function handleCurrencySelectionCallback(
        string $callbackQueryId,
        string $action,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): bool {
        foreach ([
            CallbackActions::EXPENSE_AMOUNT_CURRENCY_ACTION_PREFIX,
            CallbackActions::EXPENSE_CURRENCY_SELECT_ACTION_PREFIX,
        ] as $prefix) {
            if (str_starts_with($action, $prefix)) {
                $this->selectCurrency($callbackQueryId, $context, $draft, substr($action, strlen($prefix)));

                return true;
            }
        }

        return false;
    }

    private function handleCurrencyListCallback(
        string $callbackQueryId,
        string $action,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): bool {
        if ($action === CallbackActions::EXPENSE_AMOUNT_MORE_CURRENCIES_ACTION) {
            $this->showCurrencyList($callbackQueryId, $context, $draft);

            return true;
        }

        if (str_starts_with($action, CallbackActions::EXPENSE_CURRENCY_PAGE_ACTION_PREFIX)) {
            $this->handleCurrencyPage($callbackQueryId, $action, $context, $draft);

            return true;
        }

        if ($action === CallbackActions::EXPENSE_CURRENCY_SEARCH_ACTION) {
            $this->openCurrencySearch($callbackQueryId, $context, $draft);

            return true;
        }

        return false;
    }

    private function handleCurrencyBackCallback(
        string $callbackQueryId,
        string $action,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): bool {
        if ($action === CallbackActions::EXPENSE_CURRENCY_SEARCH_BACK_ACTION) {
            $this->showCurrencyList($callbackQueryId, $context, $draft);

            return true;
        }

        if ($action === CallbackActions::EXPENSE_CURRENCY_BACK_ACTION) {
            $this->returnToAmount($callbackQueryId, $context, $draft);

            return true;
        }

        return false;
    }

    private function showCurrencyList(
        string $callbackQueryId,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): void {
        $this->answerCallback($callbackQueryId);
        $returnStep = $draft->returnStep;
        $draft->setCurrencySearchQuery(null);
        $draft->setCurrencyPage(0);
        $draft->setStep('currency_list', null, $returnStep);
        $this->renderCurrencyList($context, $draft);
    }

    private function handleCurrencyPage(
        string $callbackQueryId,
        string $action,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): void {
        $this->answerCallback($callbackQueryId);
        $page = substr($action, strlen(CallbackActions::EXPENSE_CURRENCY_PAGE_ACTION_PREFIX));
        if (ctype_digit($page)) {
            $draft->setCurrencyPage((int) $page);
            $this->renderCurrencyList($context, $draft);
        }
    }

    private function openCurrencySearch(
        string $callbackQueryId,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): void {
        $this->answerCallback($callbackQueryId);
        $returnStep = $draft->returnStep;
        $draft->setCurrencySearchQuery(null);
        $draft->setStep('currency_search', 'currency_search', $returnStep);
        $this->renderCurrencySearch($context, $draft);
    }

    private function returnToAmount(
        string $callbackQueryId,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): void {
        $this->answerCallback($callbackQueryId);
        $returnStep = $draft->returnStep;
        $draft->setCurrencySearchQuery(null);
        $draft->setCurrencyPage(0);
        $draft->setStep('amount', $draft->amount === null ? 'amount' : null, $returnStep);
        $this->renderAmount($context, $draft);
    }

    private function selectCurrency(
        string $callbackQueryId,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        string $code
    ): void {
        try {
            $currency = CurrencyCode::fromString($code);
        } catch (InvalidValueException) {
            $this->answerCallback($callbackQueryId);

            return;
        }

        $this->answerCallback($callbackQueryId);
        $selectedCurrency = (string) ($draft->currency ?? $context->activeFamily->getDefaultExpenseCurrency());
        if ($selectedCurrency === (string) $currency && $draft->currentStep === 'amount') {
            return;
        }

        $notice = $this->applyCurrencySelection($context, $draft, $currency, $selectedCurrency);
        $this->renderAmount($context, $draft, $notice);
    }

    private function applyCurrencySelection(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        CurrencyCode $currency,
        string $selectedCurrency
    ): ?string {
        $returnStep = $draft->returnStep;
        $draft->setCurrency((string) $currency);
        $draft->setCurrencySearchQuery(null);
        $draft->setCurrencyPage(0);
        $draft->setStep('amount', $draft->amount === null ? 'amount' : null, $returnStep);

        if ($draft->amount === null || $selectedCurrency === (string) $currency) {
            return null;
        }

        return $this->translator->trans('expense.add.currency.changed_keep_amount', [
            '%{currency}' => (string) $currency,
            '%{amount}' => $this->amountFormatter->formatNumber(
                $draft->amount,
                (string) $currency,
                $context->language
            ),
        ], null, $context->language);
    }

    private function renderCurrencyList(TelegramFamilyMemberContext $context, ExpenseDraft $draft): void
    {
        $messageId = $this->screen->renderCurrencyList($context, $draft, $draft->cardMessageId);
        $draft->setCardMessage($messageId);
    }

    private function renderCurrencySearch(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?string $errorKey = null
    ): void {
        $messageId = $this->screen->renderCurrencySearch($context, $draft, $draft->cardMessageId, $errorKey);
        $draft->setCardMessage($messageId);
    }

    private function renderAmount(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?string $notice = null
    ): void {
        $messageId = $this->screen->renderAmount(
            $context,
            $draft,
            $draft->cardMessageId,
            notice: $notice
        );
        $draft->setCardMessage($messageId);
    }

    private function answerCallback(string $callbackQueryId): void
    {
        $this->telegramClient->answerCallbackQuery($callbackQueryId);
    }
}
