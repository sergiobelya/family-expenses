<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Expense\Application\Query\GetExpenseStoreQuery;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\ExpenseDraft;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ExpenseDraftScreen
{
    public function __construct(
        private ExpenseCardRenderer $cardRenderer,
        private ExpenseDraftCallbackData $callbackData,
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator,
        private MessageBusDispatcherInterface $messageBusDispatcher,
    ) {
    }

    public function renderDraft(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null
    ): int {
        $prompt = $this->trans('expense.add.store.prompt', $context);
        $error = $errorKey === null ? null : $this->trans($errorKey, $context);
        $text = $this->cardRenderer->buildCardText(
            $context->activeFamily,
            $draft,
            null,
            null,
            $prompt,
            $error
        );
        $renderedMessageId = $this->cardRenderer->render($context->chatId, $messageId, $text, []);

        return $renderedMessageId;
    }

    public function renderAmountPrompt(
        TelegramFamilyMemberContext $context,
        ExpenseDraft $draft,
        ?int $messageId,
        ?string $errorKey = null
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
        $currency = $draft->currency ?? (string) $context->activeFamily->getDefaultExpenseCurrency();
        $prompt = $this->translator->trans(
            'expense.amount.prompt',
            ['%{currency}' => $currency, '%{example_amount}' => '125.50'],
            null,
            $context->language
        );
        $error = $errorKey === null ? null : $this->trans($errorKey, $context);
        $text = $this->cardRenderer->buildCardText(
            $context->activeFamily,
            $draft,
            $store,
            null,
            $prompt,
            $error
        );

        return $this->cardRenderer->render($context->chatId, $messageId, $text, []);
    }

    public function showExpired(
        TelegramFamilyMemberContext $context,
        ?int $messageId,
        ?string $token,
        ?int $revision,
        string $messageKey = 'expense.add.expired.message'
    ): void {
        $token ??= bin2hex(random_bytes(16));
        $revision ??= 0;
        $buttons = [
            [
                'text' => $this->trans('expense.add.expired.restart', $context),
                'callback' => $this->callbackData->forValues($token, $revision, CallbackActions::EXPENSE_EXPIRED_RESTART_ACTION),
            ],
            [
                'text' => $this->trans('expense.add.expired.close', $context),
                'callback' => $this->callbackData->forValues($token, $revision, CallbackActions::EXPENSE_EXPIRED_CLOSE_ACTION),
            ],
        ];

        $this->cardRenderer->render(
            $context->chatId,
            $messageId,
            $this->trans($messageKey, $context),
            $buttons
        );
    }

    public function closeExpiredCard(
        TelegramFamilyMemberContext $context,
        ?int $messageId,
        string $messageKey = 'expense.add.expired.message'
    ): void {
        if ($messageId === null) {
            return;
        }

        $this->cardRenderer->render(
            $context->chatId,
            $messageId,
            $this->trans($messageKey, $context),
            []
        );
    }

    public function closeMovedCard(int $chatId, int $messageId): void
    {
        $this->cardRenderer->closeMovedCard($chatId, $messageId);
    }

    public function showUnexpectedInput(TelegramFamilyMemberContext $context, ExpenseDraft $draft): void
    {
        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->trans('expense.add.input.unexpected', $context),
            replyMarkup: [
                'inline_keyboard' => [[
                    [
                        'text' => $this->trans('expense.add.input.show_card', $context),
                        'callback_data' => $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_SHOW_CARD_ACTION),
                    ],
                ]],
            ]
        );
    }

    public function showSaveInProgress(TelegramFamilyMemberContext $context): void
    {
        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->trans('expense.add.save.in_progress', $context)
        );
    }

    public function showSaveUnknown(TelegramFamilyMemberContext $context, ExpenseDraft $draft): int
    {
        $messageId = $this->cardRenderer->render(
            $context->chatId,
            $draft->cardMessageId,
            $this->trans('expense.add.save.error', $context),
            [
                [
                    'text' => $this->trans('expense.add.save.retry', $context),
                    'callback' => $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_RETRY_ACTION),
                ],
                [
                    'text' => $this->trans('expense.add.save.review_draft', $context),
                    'callback' => $this->callbackData->forDraft($draft, CallbackActions::EXPENSE_REVIEW_DRAFT_ACTION),
                ],
            ]
        );

        return $messageId;
    }

    public function trans(string $key, TelegramUserContext $context): string
    {
        return $this->translator->trans($key, [], null, $context->language);
    }
}
