<?php

namespace App\Presentation\Telegram\Client;

interface TelegramApiClientInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getUpdates(?int $offset, int $timeout = 30): array;

    /**
     * @param array<string, mixed>|null $replyMarkup
     * @return array<string, mixed>
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML'
    ): array;

    /**
     * @param array<string, mixed>|null $replyMarkup
     * @return array<string, mixed>
     */
    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML'
    ): array;

    public function answerCallbackQuery(
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false
    ): bool;

    public function deleteMessage(int|string $chatId, int $messageId): bool;
}
