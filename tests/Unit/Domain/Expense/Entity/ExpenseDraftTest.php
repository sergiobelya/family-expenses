<?php

namespace App\Tests\Unit\Domain\Expense\Entity;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use App\Expense\Domain\ExpenseDraft;
use App\Expense\Domain\ExpenseDraftStatus;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpenseDraft::class)]
final class ExpenseDraftTest extends TestCase
{
    public function testCreateNewDraftIsActive(): void
    {
        $now = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));
        $draft = ExpenseDraft::createNew(
            userId: UserId::generate(),
            familyId: FamilyId::generate(),
            telegramUserId: 12345,
            chatId: 67890,
            now: $now,
        );

        self::assertSame(ExpenseDraftStatus::ACTIVE, $draft->status);
        self::assertNull($draft->storeId);
        self::assertNull($draft->amount);
        self::assertNull($draft->currency);
        self::assertEmpty($draft->categoryAllocations);
        self::assertFalse($draft->categoryCustomized);
        self::assertNull($draft->purchaseDate);
        self::assertSame(0, $draft->cardRevision);
        self::assertGreaterThan(0, strlen($draft->idempotencyKey));
        self::assertSame($now, $draft->createdAt);
        self::assertSame($now, $draft->updatedAt);
    }

    public function testSetStatusIncrementsRevision(): void
    {
        $draft = $this->createDraft();
        $draft->setStatus(ExpenseDraftStatus::PAUSED);

        self::assertSame(ExpenseDraftStatus::PAUSED, $draft->status);
        self::assertSame(1, $draft->cardRevision);
    }

    public function testSetStepIncrementsRevision(): void
    {
        $draft = $this->createDraft();
        $draft->setStep('AE-06', 'amount');

        self::assertSame('AE-06', $draft->currentStep);
        self::assertSame('amount', $draft->awaitingInput);
        self::assertSame(1, $draft->cardRevision);
    }

    public function testSettingCardMessageDoesNotInvalidateDraftCallbacks(): void
    {
        $draft = $this->createDraft();
        $draft->setStep('store');
        $revision = $draft->cardRevision;
        $draft->setCardMessage(123);

        self::assertSame(123, $draft->cardMessageId);
        self::assertSame($revision, $draft->cardRevision);
    }

    public function testSetStore(): void
    {
        $draft = $this->createDraft();
        $storeId = StoreId::generate();
        $defaultCategoryId = CategoryId::generate();
        $draft->setStore($storeId, $defaultCategoryId);

        self::assertSame($storeId, $draft->storeId);
        self::assertSame($defaultCategoryId, $draft->defaultCategoryId);
        self::assertSame(1, $draft->cardRevision);
    }

    public function testSetAmount(): void
    {
        $draft = $this->createDraft();
        $draft->setAmount('125.50', 'PLN');

        self::assertSame('125.50', $draft->amount);
        self::assertSame('PLN', $draft->currency);
        self::assertSame(1, $draft->cardRevision);
    }

    public function testSetCategoryAllocations(): void
    {
        $draft = $this->createDraft();
        $allocations = [
            ['category_id' => 'cat-uuid', 'amount' => '100.00'],
            ['category_id' => 'cat-uuid-2', 'amount' => '25.50'],
        ];
        $draft->setCategoryAllocations($allocations);

        self::assertSame($allocations, $draft->categoryAllocations);
        self::assertTrue($draft->categoryCustomized);
    }

    public function testSetPurchaseDate(): void
    {
        $draft = $this->createDraft();
        $date = new DateTimeImmutable('2026-01-15', new DateTimeZone('UTC'));
        $draft->setPurchaseDate($date);

        self::assertSame($date, $draft->purchaseDate);
    }

    public function testRefreshExpiry(): void
    {
        $draft = $this->createDraft();
        $oldRevision = $draft->cardRevision;
        $draft->refreshExpiry(86400);

        self::assertSame($oldRevision, $draft->cardRevision);
        self::assertGreaterThan(time(), $draft->expiresAt->getTimestamp());
    }

    public function testIsExpired(): void
    {
        $past = (new DateTimeImmutable())->modify('-1 second');
        $draft = ExpenseDraft::fromArray([
            'status' => 'active',
            'user_id' => UserId::generate()->toRfc4122(),
            'family_id' => FamilyId::generate()->toRfc4122(),
            'telegram_user_id' => 1,
            'chat_id' => 1,
            'current_step' => '',
            'awaiting_input' => null,
            'return_step' => null,
            'card_message_id' => null,
            'card_revision' => 0,
            'store_id' => null,
            'amount' => null,
            'currency' => null,
            'category_allocations' => [],
            'category_customized' => false,
            'purchase_date' => null,
            'idempotency_key' => 'key',
            'created_at' => $past->format(DateTimeImmutable::RFC3339),
            'updated_at' => $past->format(DateTimeImmutable::RFC3339),
            'expires_at' => $past->format(DateTimeImmutable::RFC3339),
        ]);

        self::assertTrue($draft->isExpired());
    }

    public function testToArrayAndFromArrayRoundTrip(): void
    {
        $storeId = StoreId::generate();
        $defaultCategoryId = CategoryId::generate();
        $draft = $this->createDraft();
        $draft->setStore($storeId, $defaultCategoryId);
        $draft->setStoreSearchQuery('market');
        $draft->setAmount('125.50', 'PLN');
        $draft->setStep('AE-08');
        $draft->setPurchaseDate(new DateTimeImmutable('2026-06-15', new DateTimeZone('UTC')));

        $array = $draft->toArray();
        self::assertSame('active', $array['status']);
        self::assertSame('125.50', $array['amount']);
        self::assertSame('PLN', $array['currency']);
        self::assertSame($storeId->toRfc4122(), $array['store_id']);
        self::assertSame($defaultCategoryId->toRfc4122(), $array['default_category_id']);
        self::assertSame('market', $array['store_search_query']);

        $restored = ExpenseDraft::fromArray($array);
        self::assertSame($draft->status, $restored->status);
        self::assertTrue($draft->userId->equals($restored->userId));
        self::assertTrue($draft->familyId->equals($restored->familyId));
        self::assertNotNull($draft->storeId);
        self::assertNotNull($restored->storeId);
        self::assertTrue($draft->storeId->equals($restored->storeId));
        self::assertNotNull($restored->defaultCategoryId);
        self::assertTrue($defaultCategoryId->equals($restored->defaultCategoryId));
        self::assertSame('market', $restored->storeSearchQuery);
        self::assertSame($draft->amount, $restored->amount);
        self::assertSame($draft->currency, $restored->currency);
        self::assertSame($draft->currentStep, $restored->currentStep);
        self::assertNotNull($draft->purchaseDate);
        self::assertNotNull($restored->purchaseDate);
        self::assertSame($draft->purchaseDate->getTimestamp(), $restored->purchaseDate->getTimestamp());
        self::assertSame($draft->idempotencyKey, $restored->idempotencyKey);
    }

    public function testFromArrayWithNullStore(): void
    {
        $array = [
            'status' => 'paused',
            'user_id' => UserId::generate()->toRfc4122(),
            'family_id' => FamilyId::generate()->toRfc4122(),
            'telegram_user_id' => 123,
            'chat_id' => 456,
            'current_step' => 'AE-05',
            'awaiting_input' => null,
            'return_step' => 'AE-06',
            'card_message_id' => 99,
            'card_revision' => 3,
            'store_id' => null,
            'amount' => '50.00',
            'currency' => 'EUR',
            'category_allocations' => [],
            'category_customized' => false,
            'purchase_date' => null,
            'idempotency_key' => 'abc123',
            'created_at' => '2026-01-01T12:00:00+00:00',
            'updated_at' => '2026-01-01T12:00:00+00:00',
            'expires_at' => '2026-01-02T12:00:00+00:00',
        ];

        $draft = ExpenseDraft::fromArray($array);
        self::assertSame(ExpenseDraftStatus::PAUSED, $draft->status);
        self::assertNull($draft->storeId);
        self::assertNull($draft->purchaseDate);
    }

    private function createDraft(): ExpenseDraft
    {
        return ExpenseDraft::createNew(
            userId: UserId::generate(),
            familyId: FamilyId::generate(),
            telegramUserId: 12345,
            chatId: 67890,
            now: new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')),
        );
    }
}
