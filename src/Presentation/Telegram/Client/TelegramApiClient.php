<?php

namespace App\Presentation\Telegram\Client;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class TelegramApiClient implements TelegramApiClientInterface
{
    private string $botToken;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        string $botToken,
        HttpClientInterface $httpClient,
        ?LoggerInterface $logger = null
    ) {
        $this->botToken = $botToken;
        $this->httpClient = $httpClient;
        $this->logger = $logger ?? new NullLogger();
    }

    public function sendMessage(
        int|string $chatId,
        string $text,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML'
    ): array {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->request('sendMessage', $payload);
    }

    public function getUpdates(?int $offset, int $timeout = 30): array
    {
        $payload = ['timeout' => $timeout];
        if ($offset !== null) {
            $payload['offset'] = $offset;
        }

        $response = $this->request('getUpdates', $payload, $timeout + 5);
        if (!($response['ok'] ?? false) || !is_array($response['result'] ?? null)) {
            throw new RuntimeException(sprintf(
                'Telegram getUpdates failed: %s',
                $response['description'] ?? 'invalid API response'
            ));
        }

        return $response['result'];
    }

    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML'
    ): array {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->request('editMessageText', $payload);
    }

    public function answerCallbackQuery(
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false
    ): bool {
        $payload = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert,
        ];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        $response = $this->request('answerCallbackQuery', $payload);

        return (bool) ($response['result'] ?? false);
    }

    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        $response = $this->request('deleteMessage', $payload);

        return (bool) ($response['result'] ?? false);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, array $payload, int $timeout = 10): array
    {
        $url = sprintf('https://api.telegram.org/bot%s/%s', $this->botToken, $method);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => $timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode >= 400 || !($data['ok'] ?? false)) {
                $this->logger->warning('Telegram API error response', [
                    'method' => $method,
                    'status_code' => $statusCode,
                    'response' => $data,
                ]);
            }

            return $data;
        } catch (Throwable $e) {
            $this->logger->error('Failed to execute Telegram API request', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }
}
