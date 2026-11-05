<?php

namespace App\Presentation\Telegram\DTO;

final readonly class TelegramUpdate
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public int $updateId,
        public ?int $chatId,
        public ?int $fromUserId,
        public ?string $fromUsername,
        public ?string $fromFirstName,
        public ?string $fromLastName,
        public ?string $languageCode,
        public ?int $messageId,
        public ?string $text,
        public ?string $callbackQueryId,
        public ?string $callbackData,
        public ?int $callbackMessageId,
        public array $raw = []
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $updateId = (int) ($data['update_id'] ?? 0);
        $message = $data['message'] ?? null;
        $callbackQuery = $data['callback_query'] ?? null;

        $chatId = null;
        $fromUserId = null;
        $fromUsername = null;
        $fromFirstName = null;
        $fromLastName = null;
        $languageCode = null;
        $messageId = null;
        $text = null;
        $callbackQueryId = null;
        $callbackData = null;
        $callbackMessageId = null;

        if (is_array($callbackQuery)) {
            $callbackQueryId = (string) ($callbackQuery['id'] ?? '');
            $callbackData = isset($callbackQuery['data']) ? (string) $callbackQuery['data'] : null;

            $from = $callbackQuery['from'] ?? null;
            if (is_array($from)) {
                $fromUserId = (int) ($from['id'] ?? 0);
                $fromUsername = $from['username'] ?? null;
                $fromFirstName = $from['first_name'] ?? null;
                $fromLastName = $from['last_name'] ?? null;
                $languageCode = $from['language_code'] ?? null;
            }

            $cbMessage = $callbackQuery['message'] ?? null;
            if (is_array($cbMessage)) {
                $callbackMessageId = (int) ($cbMessage['message_id'] ?? 0);
                $chat = $cbMessage['chat'] ?? null;
                if (is_array($chat)) {
                    $chatId = (int) ($chat['id'] ?? 0);
                }
            }
        } elseif (is_array($message)) {
            $messageId = (int) ($message['message_id'] ?? 0);
            $text = isset($message['text']) ? (string) $message['text'] : null;

            $chat = $message['chat'] ?? null;
            if (is_array($chat)) {
                $chatId = (int) ($chat['id'] ?? 0);
            }

            $from = $message['from'] ?? null;
            if (is_array($from)) {
                $fromUserId = (int) ($from['id'] ?? 0);
                $fromUsername = $from['username'] ?? null;
                $fromFirstName = $from['first_name'] ?? null;
                $fromLastName = $from['last_name'] ?? null;
                $languageCode = $from['language_code'] ?? null;
            }
        }

        return new self(
            updateId: $updateId,
            chatId: $chatId,
            fromUserId: $fromUserId,
            fromUsername: $fromUsername,
            fromFirstName: $fromFirstName,
            fromLastName: $fromLastName,
            languageCode: $languageCode,
            messageId: $messageId,
            text: $text,
            callbackQueryId: $callbackQueryId,
            callbackData: $callbackData,
            callbackMessageId: $callbackMessageId,
            raw: $data
        );
    }

    public function isCallbackQuery(): bool
    {
        return $this->callbackQueryId !== null;
    }

    public function isMessage(): bool
    {
        return $this->messageId !== null;
    }
}
