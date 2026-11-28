<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\Exception\InvalidValueException;
use App\Expense\Domain\ExpenseDraft;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\ExceptionMapper\CoreExceptionMapper;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ExpenseAmountEntryStep implements AddExpenseStepInterface
{
    public function __construct(
        private AmountParser $amountParser,
        private CoreExceptionMapper $exceptionMapper,
        private ExpenseAmountScreen $screen,
        private ExpenseCurrencySelectionFlow $currencySelectionFlow,
        private ExpenseStorePickerScreen $storePickerScreen,
        private ExpenseDraftScreen $draftScreen,
        private ExpenseDraftCallbackData $callbackData,
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(ExpenseDraft $draft): bool
    {
        return $draft->currentStep === 'amount' || $this->currencySelectionFlow->supports($draft);
    }

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): ExpenseDraft {
        if ($update->callbackData !== null) {
            return $this->handleCallback($update, $context, $draft);
        }

        return $this->handleTextInput($update, $context, $draft);
    }

    public function render(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null
    ): int {
        return $this->currencySelectionFlow->supports($draft)
            ? $this->currencySelectionFlow->render($context, $draft, $messageId, $errorKey)
            : $this->screen->renderAmount($context, $draft, $messageId, $errorKey);
    }

    private function handleTextInput(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): ExpenseDraft {
        if ($this->currencySelectionFlow->handleTextInput($context, $draft, $update->text)) {
            return $draft;
        }

        if ($draft->currentStep === 'amount' && $update->text !== null) {
            return $this->handleAmountInput($context, $draft, $update->text);
        }

        $this->draftScreen->showUnexpectedInput($context, $draft);

        return $draft;
    }

    private function handleAmountInput(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        string $rawAmount
    ): ExpenseDraft {
        $currency = $draft->currency ?? (string) $context->activeFamily->getDefaultExpenseCurrency();
        try {
            $amount = $this->amountParser->parse($rawAmount, $currency);
        } catch (InvalidValueException $exception) {
            $message = $this->exceptionMapper->map($exception);
            $parameters = $message->getParameters();
            $parameters['%{currency}'] = $currency;
            $error = $this->translator->trans(
                $message->getMessage(),
                $parameters,
                null,
                $context->language
            );
            $this->renderAmount($context, $draft, errorText: $error);

            return $draft;
        }

        $draft->setAmount($amount->toDecimalString(), $currency);
        $draft->setStep($draft->returnStep ?? 'category');
        $this->renderNextStep($context, $draft);

        return $draft;
    }

    private function handleCallback(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): ExpenseDraft {
        $callbackQueryId = $update->callbackQueryId;
        $callback = $this->callbackData->parse($update->callbackData ?? '');
        if ($callbackQueryId === null || $callback === null) {
            return $draft;
        }

        if ($this->currencySelectionFlow->handleCallback($callbackQueryId, $callback['action'], $context, $draft)) {
            return $draft;
        }

        return $this->dispatchCallback($callbackQueryId, $callback['action'], $context, $draft);
    }

    private function dispatchCallback(
        string $callbackQueryId,
        string $action,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): ExpenseDraft {
        if ($this->handleAmountNavigationCallback($callbackQueryId, $action, $context, $draft)) {
            return $draft;
        }

        $this->answerCallback($callbackQueryId);

        return $draft;
    }

    private function handleAmountNavigationCallback(
        string $callbackQueryId,
        string $action,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): bool {
        if ($action === CallbackActions::EXPENSE_AMOUNT_CONTINUE_ACTION) {
            $this->continueFromAmountStep($callbackQueryId, $context, $draft);
            return true;
        }

        if (
            $action === CallbackActions::EXPENSE_AMOUNT_BACK_ACTION
            || $action === CallbackActions::EXPENSE_AMOUNT_BACK_REVIEW_ACTION
        ) {
            $this->returnFromAmountStep($callbackQueryId, $action, $context, $draft);
            return true;
        }

        return false;
    }

    private function continueFromAmountStep(
        string $callbackQueryId,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): void {
        $this->answerCallback($callbackQueryId);
        $currency = $draft->currency ?? (string) $context->activeFamily->getDefaultExpenseCurrency();
        if (
            $draft->amount === null
            || $this->amountParser->exceedsCurrencyPrecision($draft->amount, $currency)
        ) {
            $this->renderAmount($context, $draft);

            return;
        }

        $draft->setStep($draft->returnStep ?? 'category');
        $this->renderNextStep($context, $draft);
    }

    private function returnFromAmountStep(
        string $callbackQueryId,
        string $action,
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft
    ): void {
        $this->answerCallback($callbackQueryId);
        $isReviewBack = $action === CallbackActions::EXPENSE_AMOUNT_BACK_REVIEW_ACTION;
        $draft->setStep($isReviewBack ? 'review' : 'store');
        if (!$isReviewBack) {
            $messageId = $this->storePickerScreen->renderList($context, $draft, $draft->cardMessageId);
            $draft->setCardMessage($messageId);
        }
    }

    private function renderAmount(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?string $errorText = null,
        ?string $notice = null
    ): void {
        $messageId = $this->screen->renderAmount(
            $context,
            $draft,
            $draft->cardMessageId,
            errorText: $errorText,
            notice: $notice
        );
        $draft->setCardMessage($messageId);
    }

    private function renderNextStep(TelegramFamilyMemberContext $context, ExpenseDraft $draft): void
    {
        $messageId = match ($draft->currentStep) {
            'store' => $this->storePickerScreen->renderList($context, $draft, $draft->cardMessageId),
            'category' => $this->draftScreen->renderCategoryPrompt($context, $draft, $draft->cardMessageId),
            default => $draft->cardMessageId ?? throw new \LogicException(
                sprintf('No renderer is available for expense draft step "%s".', $draft->currentStep)
            ),
        };
        $draft->setCardMessage($messageId);
    }

    private function answerCallback(string $callbackQueryId): void
    {
        $this->telegramClient->answerCallbackQuery($callbackQueryId);
    }
}
