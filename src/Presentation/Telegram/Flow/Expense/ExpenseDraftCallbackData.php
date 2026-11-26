<?php

namespace App\Presentation\Telegram\Flow\Expense;

use App\Expense\Domain\ExpenseDraft;
use App\Core\Domain\ValueObject\StoreId;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use Symfony\Component\Uid\Uuid;

final readonly class ExpenseDraftCallbackData
{
    /**
     * @return array{token: string, revision: int, action: string}|null
     */
    public function parse(string $callbackData): ?array
    {
        if (
            strlen($callbackData) > 64
            || preg_match('/^exp:t:([a-zA-Z0-9_-]{1,32}):(\d+):(.+)$/D', $callbackData, $matches) !== 1
        ) {
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
        $tokenMatches = hash_equals($draft->idempotencyKey, $callback['token'])
            || $this->matchesStoreToken($callback, $draft);

        return $tokenMatches
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

    public function forStore(ExpenseDraft $draft, StoreId $storeId): string
    {
        $encodedStoreId = rtrim(strtr(base64_encode($storeId->toUuid()->toBinary()), '+/', '-_'), '=');
        $action = CallbackActions::EXPENSE_STORE_SELECT_ACTION_PREFIX . $encodedStoreId;
        $token = $this->storeToken($draft, $draft->cardRevision, $action);

        return $this->forValues(
            $token,
            $draft->cardRevision,
            $action
        );
    }

    public function storeIdFromAction(string $action): ?StoreId
    {
        if (!str_starts_with($action, CallbackActions::EXPENSE_STORE_SELECT_ACTION_PREFIX)) {
            return null;
        }

        $encodedStoreId = substr($action, strlen(CallbackActions::EXPENSE_STORE_SELECT_ACTION_PREFIX));
        $binaryStoreId = base64_decode(strtr($encodedStoreId, '-_', '+/'), true);
        if ($binaryStoreId === false || strlen($binaryStoreId) !== 16) {
            return null;
        }

        try {
            return StoreId::fromString(Uuid::fromBinary($binaryStoreId)->toRfc4122());
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @param array{token: string, revision: int, action: string} $callback */
    private function matchesStoreToken(array $callback, ExpenseDraft $draft): bool
    {
        if (!str_starts_with($callback['action'], CallbackActions::EXPENSE_STORE_SELECT_ACTION_PREFIX)) {
            return false;
        }

        return hash_equals(
            $this->storeToken($draft, $callback['revision'], $callback['action']),
            $callback['token']
        );
    }

    private function storeToken(ExpenseDraft $draft, int $revision, string $action): string
    {
        $maximumTokenLength = 64
            - strlen(CallbackActions::EXPENSE_TOKEN_PREFIX)
            - strlen((string) $revision)
            - strlen($action)
            - 2;
        $tokenLength = min(22, $maximumTokenLength);

        return substr(hash('sha256', $draft->idempotencyKey), 0, $tokenLength);
    }
}
