<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Expense\Domain\ExpenseDraft;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\DTO\TelegramUpdate;

final readonly class ExpenseDraftCallbackData
{
    /**
     * @return array{token: string, revision: int, action: string}|null
     */
    public function parse(string $callbackData): ?array
    {
        if (preg_match('/^exp:t:([a-zA-Z0-9_-]{1,32}):(\d+):(.+)$/D', $callbackData, $matches) !== 1) {
            return null;
        }

        return [
            'token' => $matches[1],
            'revision' => (int) $matches[2],
            'action' => $matches[3],
        ];
    }

    /**
     * @param array{token: string, revision: int, action: string} $callback
     */
    public function matchesDraft(array $callback, ExpenseDraft $draft, TelegramUpdate $update): bool
    {
        return hash_equals($draft->idempotencyKey, $callback['token'])
            && $draft->cardRevision === $callback['revision']
            && ($draft->cardMessageId === null || $draft->cardMessageId === $update->callbackMessageId);
    }

    public function forDraft(ExpenseDraft $draft, string $action): string
    {
        return $this->forValues($draft->idempotencyKey, $draft->cardRevision, $action);
    }

    public function forValues(string $token, int $revision, string $action): string
    {
        return CallbackActions::EXPENSE_TOKEN_PREFIX . $token . ':' . $revision . ':' . $action;
    }
}
