<?php

namespace App\Expense\Domain;

use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Expense draft data stored in Redis.
 *
 * Mutable DTO — modified in-place by Telegram flow handlers.
 * Scoped to (user_id, family_id) tuple — at most one non-terminal draft per tuple.
 * Active/Paused drafts expire 24 hours after last meaningful change.
 *
 * @see docs/specs/add_expense.md §8.1
 */
final class ExpenseDraft
{
    private function __construct(
        public ExpenseDraftStatus $status,
        public UserId $userId,
        public FamilyId $familyId,
        public int $telegramUserId,
        public int $chatId,
        public string $currentStep,
        public ?string $awaitingInput,
        public ?string $returnStep,
        public ?int $cardMessageId,
        public int $cardRevision,
        public ?StoreId $storeId,
        public ?string $amount,
        public ?string $currency,
        /** @var list<array{category_id: string, amount: string}> */
        public array $categoryAllocations,
        public bool $categoryCustomized,
        public ?DateTimeImmutable $purchaseDate,
        public string $idempotencyKey,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * Create a new draft in Active state.
     */
    public static function createNew(
        UserId $userId,
        FamilyId $familyId,
        int $telegramUserId,
        int $chatId,
        DateTimeImmutable $now,
        int $ttlSeconds = 86400,
    ): self {
        return new self(
            status: ExpenseDraftStatus::ACTIVE,
            userId: $userId,
            familyId: $familyId,
            telegramUserId: $telegramUserId,
            chatId: $chatId,
            currentStep: '',
            awaitingInput: null,
            returnStep: null,
            cardMessageId: null,
            cardRevision: 0,
            storeId: null,
            amount: null,
            currency: null,
            categoryAllocations: [],
            categoryCustomized: false,
            purchaseDate: null,
            idempotencyKey: self::generateIdempotencyKey(),
            createdAt: $now,
            updatedAt: $now,
            expiresAt: $now->modify("+{$ttlSeconds} seconds"),
        );
    }

    public function setStatus(ExpenseDraftStatus $status): void
    {
        $this->status = $status;
        $this->cardRevision++;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setStep(string $step, ?string $awaitingInput = null, ?string $returnStep = null): void
    {
        $this->currentStep = $step;
        $this->awaitingInput = $awaitingInput;
        $this->returnStep = $returnStep;
        $this->cardRevision++;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Keep callbacks on a re-rendered card valid without changing the draft revision when its Telegram message changes.
     */
    public function setCardMessage(int $messageId): void
    {
        $this->cardMessageId = $messageId;
    }

    public function setStore(StoreId $storeId): void
    {
        $this->storeId = $storeId;
        $this->cardRevision++;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setAmount(string $amount, string $currency): void
    {
        $this->amount = $amount;
        $this->currency = $currency;
        $this->cardRevision++;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @param list<array{category_id: string, amount: string}> $allocations
     */
    public function setCategoryAllocations(array $allocations, bool $customized = true): void
    {
        $this->categoryAllocations = $allocations;
        $this->categoryCustomized = $customized;
        $this->cardRevision++;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setPurchaseDate(DateTimeImmutable $date): void
    {
        $this->purchaseDate = $date;
        $this->cardRevision++;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Refresh the expiry time (24 hours from now).
     */
    public function refreshExpiry(int $ttlSeconds = 86400): void
    {
        $this->expiresAt = (new DateTimeImmutable())->modify("+{$ttlSeconds} seconds");
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return (new DateTimeImmutable()) >= $this->expiresAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'user_id' => $this->userId->toRfc4122(),
            'family_id' => $this->familyId->toRfc4122(),
            'telegram_user_id' => $this->telegramUserId,
            'chat_id' => $this->chatId,
            'current_step' => $this->currentStep,
            'awaiting_input' => $this->awaitingInput,
            'return_step' => $this->returnStep,
            'card_message_id' => $this->cardMessageId,
            'card_revision' => $this->cardRevision,
            'store_id' => $this->storeId?->toRfc4122(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'category_allocations' => $this->categoryAllocations,
            'category_customized' => $this->categoryCustomized,
            'purchase_date' => $this->purchaseDate?->format(DateTimeImmutable::RFC3339),
            'idempotency_key' => $this->idempotencyKey,
            'created_at' => $this->createdAt->format(DateTimeImmutable::RFC3339),
            'updated_at' => $this->updatedAt->format(DateTimeImmutable::RFC3339),
            'expires_at' => $this->expiresAt->format(DateTimeImmutable::RFC3339),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            status: ExpenseDraftStatus::from((string) ($payload['status'] ?? 'active')),
            userId: UserId::fromString((string) ($payload['user_id'] ?? '')),
            familyId: FamilyId::fromString((string) ($payload['family_id'] ?? '')),
            telegramUserId: (int) ($payload['telegram_user_id'] ?? 0),
            chatId: (int) ($payload['chat_id'] ?? 0),
            currentStep: (string) ($payload['current_step'] ?? ''),
            awaitingInput: $payload['awaiting_input'] ?? null,
            returnStep: $payload['return_step'] ?? null,
            cardMessageId: $payload['card_message_id'] ?? null,
            cardRevision: (int) ($payload['card_revision'] ?? 0),
            storeId: isset($payload['store_id']) ? StoreId::fromString((string) $payload['store_id']) : null,
            amount: $payload['amount'] ?? null,
            currency: $payload['currency'] ?? null,
            categoryAllocations: self::parseAllocations($payload),
            categoryCustomized: (bool) ($payload['category_customized'] ?? false),
            purchaseDate: self::parseDate($payload['purchase_date'] ?? null),
            idempotencyKey: (string) ($payload['idempotency_key'] ?? ''),
            createdAt: self::parseRequiredDate($payload['created_at'] ?? '2026-01-01T00:00:00+00:00'),
            updatedAt: self::parseRequiredDate($payload['updated_at'] ?? '2026-01-01T00:00:00+00:00'),
            expiresAt: self::parseRequiredDate($payload['expires_at'] ?? '2026-01-01T00:00:00+00:00'),
        );
    }

    private static function generateIdempotencyKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{category_id: string, amount: string}>
     */
    private static function parseAllocations(array $payload): array
    {
        $raw = $payload['category_allocations'] ?? [];
        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $item) {
            if (is_array($item) && isset($item['category_id'], $item['amount'])) {
                $result[] = [
                    'category_id' => (string) $item['category_id'],
                    'amount' => (string) $item['amount'],
                ];
            }
        }

        return $result;
    }

    private static function parseDate(?string $value): ?DateTimeImmutable
    {
        if (!$value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339, $value);
        return $date ?: new DateTimeImmutable($value);
    }

    private static function parseRequiredDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339, $value);
        return $date ?: new DateTimeImmutable($value);
    }
}
