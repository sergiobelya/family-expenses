<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\Entity\ExpenseCategory;
use App\Family\Domain\Entity\Family;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Renders the expense draft card text and handles Telegram message edits.
 *
 * Follows the one-card model: one editable message that is updated
 * at each step of the expense flow.
 */
final readonly class ExpenseCardRenderer
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator,
        private MoneyAmountFormatter $amountFormatter,
    ) {
    }

    /**
     * Renders the expense card and sends or edits the Telegram message.
     *
     * @param list<array{text: string, callback: string, row?: int}> $buttons
     * @return int The message ID of the sent/edited message
     */
    public function render(
        int $chatId,
        ?int $messageId,
        string $text,
        array $buttons,
    ): int {
        $keyboard = $this->buildKeyboard($buttons);

        if ($messageId !== null) {
            try {
                $response = $this->telegramClient->editMessageText(
                    chatId: $chatId,
                    messageId: $messageId,
                    text: $text,
                    replyMarkup: $keyboard ?? ['inline_keyboard' => []],
                );

                if (($response['ok'] ?? true) === false) {
                    if (str_contains(strtolower((string) ($response['description'] ?? '')), 'message is not modified')) {
                        return $messageId;
                    }

                    return $this->sendReplacementCard($chatId, $messageId, $text, $keyboard);
                }

                return (int) ($response['result']['message_id'] ?? $messageId);
            } catch (\Throwable $exception) {
                if (str_contains(strtolower($exception->getMessage()), 'message is not modified')) {
                    return $messageId;
                }

                return $this->sendReplacementCard($chatId, $messageId, $text, $keyboard);
            }
        }

        return $this->sendNewCard($chatId, $text, $keyboard);
    }

    /**
     * Builds the card text body from the draft data.
     *
     * @param list<array{category: ExpenseCategory, amount: string}>|null $allocations
     */
    public function buildCardText(
        Family $family,
        ExpenseDraft $draft,
        ?ExpenseStore $store,
        ?array $allocations,
        string $prompt,
        ?string $error,
    ): string {
        $lines = [];
        $familyName = (string) $family->getName();
        $lines[] = $this->translator->trans('expense.add.card_header', [
            'family_name' => $this->escape($familyName),
        ]);
        $lines[] = '';

        // Store line
        $storeText = $store === null
            ? $this->translator->trans('expense.add.store.empty')
            : $this->escape($store->getName());
        $lines[] = '🏪 ' . $this->translator->trans('expense.add.store.label') . ': ' . $storeText;

        // Amount line
        if ($draft->amount !== null && $draft->currency !== null) {
            $amountText = $this->amountFormatter->format($draft->amount, $draft->currency);
        } else {
            $amountText = $this->translator->trans('expense.amount.empty');
        }
        $lines[] = '💰 ' . $this->translator->trans('expense.add.amount.label') . ': ' . $amountText;

        // Category line
        if ($allocations !== null && count($allocations) > 0) {
            if (count($allocations) === 1) {
                $a = $allocations[0];
                $lines[] = '🏷 ' . $this->translator->trans('expense.add.category.label') . ': '
                    . $this->escape($a['category']->getName());
            } else {
                $lines[] = '🏷 ' . $this->translator->trans('expense.add.category.label') . ':';
                foreach ($allocations as $a) {
                    $lines[] = '  • ' . $this->escape($a['category']->getName())
                        . ' — ' . $this->amountFormatter->format($a['amount'], $draft->currency ?? 'PLN');
                }
            }
        } else {
            $lines[] = '🏷 ' . $this->translator->trans('expense.add.category.label') . ': '
                . $this->translator->trans('expense.add.category.empty');
        }

        // Date line
        $dateText = $this->formatDate($draft->purchaseDate, (string) $family->getTimezone());
        $lines[] = '📅 ' . $dateText;
        $lines[] = '';

        // Error line
        if ($error !== null) {
            $lines[] = '⚠️ ' . $error;
            $lines[] = '';
        }

        // Prompt
        $lines[] = $prompt;

        return implode("\n", $lines);
    }

    /**
     * Formats a date for display: "26 Oct 2026 (today)".
     */
    public function formatDate(?DateTimeImmutable $date, ?string $timezone = null): string
    {
        if ($date === null) {
            return $this->translator->trans('expense.add.date.empty');
        }

        $dateTimezone = new DateTimeZone($timezone ?? date_default_timezone_get());
        $date = $date->setTimezone($dateTimezone);
        $text = $date->format('j M Y');

        // Check if today or yesterday
        $today = new DateTimeImmutable('today', $dateTimezone);
        $yesterday = $today->modify('-1 day');

        if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
            $text .= ' (' . $this->translator->trans('expense.add.date.today') . ')';
        } elseif ($date->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            $text .= ' (' . $this->translator->trans('expense.add.date.yesterday') . ')';
        }

        return $text;
    }

    public function closeMovedCard(int $chatId, int $messageId): void
    {
        try {
            $this->telegramClient->editMessageText(
                chatId: $chatId,
                messageId: $messageId,
                text: $this->translator->trans('expense.add.card.moved'),
                replyMarkup: ['inline_keyboard' => []],
            );
        } catch (\Throwable) {
        }
    }

    /** @param array<string, mixed>|null $keyboard */
    private function sendReplacementCard(int $chatId, int $previousMessageId, string $text, ?array $keyboard): int
    {
        $newMessageId = $this->sendNewCard($chatId, $text, $keyboard);
        if ($newMessageId !== $previousMessageId) {
            $this->closeMovedCard($chatId, $previousMessageId);
        }

        return $newMessageId;
    }

    /** @param array<string, mixed>|null $keyboard */
    private function sendNewCard(int $chatId, string $text, ?array $keyboard): int
    {
        $response = $this->telegramClient->sendMessage(
            chatId: $chatId,
            text: $text,
            replyMarkup: $keyboard,
        );
        $messageId = $response['result']['message_id'] ?? null;

        if (!is_int($messageId) && !(is_string($messageId) && ctype_digit($messageId))) {
            throw new RuntimeException('Telegram did not return the sent message ID.');
        }

        return (int) $messageId;
    }

    /**
     * @param list<array{text: string, callback: string}> $buttons
     * @return array{inline_keyboard: list<list<array{text: string, callback_data?: string, url?: string}>>}|null
     */
    private function buildKeyboard(array $buttons): ?array
    {
        if ($buttons === []) {
            return null;
        }

        $builder = new InlineKeyboardBuilder();
        foreach ($buttons as $btn) {
            $builder->button($btn['text'], $btn['callback']);
        }
        return $builder->toArray();
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
