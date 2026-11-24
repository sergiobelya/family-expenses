<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Core\Domain\ValueObject\MoneyAmount;
use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\Entity\ExpenseStore;
use App\Expense\Domain\Entity\ExpenseCategory;
use App\Family\Domain\Entity\Family;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
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
                    replyMarkup: $keyboard,
                );
                return (int) ($response['result']['message_id'] ?? $messageId);
            } catch (\Throwable) {
                // If edit fails (e.g., message not found), send a new one
                $response = $this->telegramClient->sendMessage(
                    chatId: $chatId,
                    text: $text,
                    replyMarkup: $keyboard,
                );
                return (int) $response['result']['message_id'];
            }
        }

        $response = $this->telegramClient->sendMessage(
            chatId: $chatId,
            text: $text,
            replyMarkup: $keyboard,
        );
        return (int) $response['result']['message_id'];
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
        $lines[] = $this->translator->trans('expense.add.card_header', [
            'family_name' => (string) $family->getName(),
        ]);
        $lines[] = '';

        // Store line
        $storeText = $store?->getName() ?? $this->translator->trans('expense.add.store.empty');
        $lines[] = '🏪 ' . $this->translator->trans('expense.add.store.label') . ': ' . $storeText;

        // Amount line
        if ($draft->amount !== null && $draft->currency !== null) {
            $lines[] = '💰 ' . $this->formatAmount($draft->amount, $draft->currency);
        } else {
            $lines[] = '💰 ' . $this->translator->trans('expense.amount.empty');
        }

        // Category line
        if ($allocations !== null && count($allocations) > 0) {
            if (count($allocations) === 1) {
                $a = $allocations[0];
                $lines[] = '🏷 ' . $this->translator->trans('expense.add.category.label') . ': '
                    . $a['category']->getName();
            } else {
                $lines[] = '🏷 ' . $this->translator->trans('expense.add.category.label') . ':';
                foreach ($allocations as $a) {
                    $lines[] = '  • ' . $a['category']->getName()
                        . ' — ' . $this->formatAmount($a['amount'], $draft->currency ?? 'PLN');
                }
            }
        } else {
            $lines[] = '🏷 ' . $this->translator->trans('expense.add.category.label') . ': '
                . $this->translator->trans('expense.add.category.empty');
        }

        // Date line
        $dateText = $this->formatDate($draft->purchaseDate);
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
     * Formats an amount for display: "125.50 PLN".
     */
    public function formatAmount(string $amount, string $currencyCode): string
    {
        $precision = MoneyAmount::getMaxPrecision($currencyCode);
        if ($precision === 0) {
            // bcadd with scale 0 truncates the integer part incorrectly, so drop
            // the fractional part manually for zero-precision currencies.
            $formatted = explode('.', $amount)[0];
        } else {
            // @phpstan-ignore argument.type
            $formatted = rtrim(rtrim(bcadd($amount, '0', $precision), '0'), '.');
            // Pad trailing zeros to match currency precision without using float cast,
            // which loses precision for numbers > 15 digits (IEEE 754 double limit).
            $parts = explode('.', $formatted);
            $decimal = $parts[1] ?? '';
            $decimal = str_pad($decimal, $precision, '0');
            $formatted = $parts[0] . '.' . $decimal;
        }
        return $formatted . ' ' . $currencyCode;
    }

    /**
     * Formats a date for display: "26 Oct 2026 (today)".
     */
    public function formatDate(?\DateTimeImmutable $date): string
    {
        if ($date === null) {
            return $this->translator->trans('expense.add.date.empty');
        }

        $text = $date->format('j M Y');

        // Check if today or yesterday
        $today = new \DateTimeImmutable('today');
        $yesterday = (clone $today)->modify('-1 day');

        if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
            $text .= ' (' . $this->translator->trans('expense.add.date.today') . ')';
        } elseif ($date->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            $text .= ' (' . $this->translator->trans('expense.add.date.yesterday') . ')';
        }

        return $text;
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
}
