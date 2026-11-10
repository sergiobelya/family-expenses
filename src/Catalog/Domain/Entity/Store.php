<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\StoreChanged;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

class Store extends AggregateRoot
{
    private StoreId $id;
    private StoreName $name;
    private string $dedupeKey;
    private ?CategoryId $defaultCategoryId;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        private FamilyId $familyId,
        StoreName $name,
        ?CategoryId $defaultCategoryId,
        ?StoreId $id,
        DateTimeImmutable $now
    ) {
        $this->id = $id ?? StoreId::generate();
        $this->name = $name;
        $this->dedupeKey = $name->dedupeKey;
        $this->defaultCategoryId = $defaultCategoryId;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public static function create(
        FamilyId $familyId,
        string $name,
        ?UserId $createdByUserId = null,
        ?StoreId $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $store = new self(
            $familyId,
            StoreName::fromString($name),
            null,
            $id,
            $now
        );
        $store->recordChange('store.created', null, $store->snapshot(), $createdByUserId, $now);

        return $store;
    }

    public function getId(): StoreId
    {
        return $this->id;
    }

    public function getFamilyId(): FamilyId
    {
        return $this->familyId;
    }

    public function getName(): string
    {
        return $this->name->value;
    }

    public function getDedupeKey(): string
    {
        return $this->dedupeKey;
    }

    public function getDefaultCategoryId(): ?CategoryId
    {
        return $this->defaultCategoryId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function rename(string $name, ?UserId $actorUserId = null, ?DateTimeImmutable $now = null): void
    {
        $storeName = StoreName::fromString($name);
        if ($this->name->value === $storeName->value && $this->dedupeKey === $storeName->dedupeKey) {
            return;
        }

        $before = $this->snapshot();
        $this->name = $storeName;
        $this->dedupeKey = $storeName->dedupeKey;
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordChange('store.renamed', $before, $this->snapshot(), $actorUserId, $this->updatedAt);
    }

    public function setDefaultCategory(
        ?CategoryId $categoryId,
        ?UserId $actorUserId = null,
        ?DateTimeImmutable $now = null
    ): void {
        $newCategoryId = $categoryId;
        if ($this->defaultCategoryId?->equals($newCategoryId) ?? $newCategoryId === null) {
            return;
        }

        $before = $this->snapshot();
        $this->defaultCategoryId = $newCategoryId;
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordChange('store.default_category_changed', $before, $this->snapshot(), $actorUserId, $this->updatedAt);
    }

    public function delete(?UserId $actorUserId = null, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordChange('store.deleted', $this->snapshot(), null, $actorUserId, $now);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'family_id' => (string) $this->familyId,
            'name' => $this->name->value,
            'dedupe_key' => $this->dedupeKey,
            'default_category_id' => $this->defaultCategoryId === null ? null : (string) $this->defaultCategoryId,
        ];
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    private function recordChange(
        string $action,
        ?array $before,
        ?array $after,
        ?UserId $actorUserId,
        DateTimeImmutable $occurredAt
    ): void {
        $this->recordEvent(new StoreChanged(
            action: $action,
            entityId: $this->id,
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            beforeData: $before,
            afterData: $after,
            occurredAt: $occurredAt
        ));
    }
}
