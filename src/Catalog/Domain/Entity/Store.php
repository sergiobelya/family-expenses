<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\CatalogChanged;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Core\Domain\Aggregate\AggregateRoot;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

class Store extends AggregateRoot
{
    private Uuid $id;
    private string $name;
    private string $dedupeKey;
    private ?Uuid $defaultCategoryId;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        private Uuid $familyId,
        CatalogName $name,
        ?Uuid $defaultCategoryId,
        ?Uuid $id,
        DateTimeImmutable $now
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->name = $name->value;
        $this->dedupeKey = $name->dedupeKey;
        $this->defaultCategoryId = $defaultCategoryId;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public static function create(
        Uuid $familyId,
        string $name,
        ?Uuid $createdByUserId = null,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $store = new self($familyId, CatalogName::fromString($name), null, $id, $now);
        $store->recordChange('store.created', null, $store->snapshot(), $createdByUserId, $now);

        return $store;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFamilyId(): Uuid
    {
        return $this->familyId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDedupeKey(): string
    {
        return $this->dedupeKey;
    }

    public function getDefaultCategoryId(): ?Uuid
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

    public function rename(string $name, ?Uuid $actorUserId = null, ?DateTimeImmutable $now = null): void
    {
        $catalogName = CatalogName::fromString($name);
        if ($this->name === $catalogName->value && $this->dedupeKey === $catalogName->dedupeKey) {
            return;
        }

        $before = $this->snapshot();
        $this->name = $catalogName->value;
        $this->dedupeKey = $catalogName->dedupeKey;
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordChange('store.renamed', $before, $this->snapshot(), $actorUserId, $this->updatedAt);
    }

    public function setDefaultCategory(
        ?Uuid $categoryId,
        ?Uuid $actorUserId = null,
        ?DateTimeImmutable $now = null
    ): void {
        if ($this->defaultCategoryId?->equals($categoryId) ?? $categoryId === null) {
            return;
        }

        $before = $this->snapshot();
        $this->defaultCategoryId = $categoryId;
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordChange('store.default_category_changed', $before, $this->snapshot(), $actorUserId, $this->updatedAt);
    }

    public function delete(?Uuid $actorUserId = null, ?DateTimeImmutable $now = null): void
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
            'name' => $this->name,
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
        ?Uuid $actorUserId,
        DateTimeImmutable $occurredAt
    ): void {
        $this->recordEvent(new CatalogChanged(
            action: $action,
            entityType: 'store',
            entityId: (string) $this->id,
            familyId: $this->familyId,
            actorUserId: $actorUserId,
            beforeData: $before,
            afterData: $after,
            occurredAt: $occurredAt
        ));
    }
}
