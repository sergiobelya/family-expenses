<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\StoreCreated;
use App\Catalog\Domain\Event\StoreDefaultCategoryChanged;
use App\Catalog\Domain\Event\StoreDeleted;
use App\Catalog\Domain\Event\StoreRenamed;
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
        $this->dedupeKey = $name->getDedupeKey();
        $this->defaultCategoryId = $defaultCategoryId;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public static function create(
        FamilyId $familyId,
        string $name,
        UserId $createdByUserId,
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
        $store->recordEvent(new StoreCreated(
            entityId: $store->getId(),
            familyId: $store->getFamilyId(),
            actorUserId: $createdByUserId,
            afterData: $store->snapshot(),
            occurredAt: $now
        ));

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
        return $this->name->getValue();
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

    public function rename(string $name, UserId $actorUserId, ?DateTimeImmutable $now = null): void
    {
        $storeName = StoreName::fromString($name);
        if ($this->name->getValue() === $storeName->getValue() && $this->dedupeKey === $storeName->getDedupeKey()) {
            return;
        }

        $before = $this->snapshot();
        $this->name = $storeName;
        $this->dedupeKey = $storeName->getDedupeKey();
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordEvent(new StoreRenamed(
            entityId: $this->id,
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            beforeData: $before,
            afterData: $this->snapshot(),
            occurredAt: $this->updatedAt
        ));
    }

    public function setDefaultCategory(
        ?CategoryId $categoryId,
        UserId $actorUserId,
        ?DateTimeImmutable $now = null
    ): void {
        $newCategoryId = $categoryId;
        if (
            $newCategoryId === null
                ? $this->defaultCategoryId === null
                : $this->defaultCategoryId?->equals($newCategoryId) === true
        ) {
            return;
        }

        $before = $this->snapshot();
        $this->defaultCategoryId = $newCategoryId;
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordEvent(new StoreDefaultCategoryChanged(
            entityId: $this->id,
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            beforeData: $before,
            afterData: $this->snapshot(),
            occurredAt: $this->updatedAt
        ));
    }

    public function delete(UserId $actorUserId, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordEvent(new StoreDeleted(
            entityId: $this->id,
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            beforeData: $this->snapshot(),
            occurredAt: $now
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'family_id' => (string) $this->familyId,
            'name' => $this->name->getValue(),
            'dedupe_key' => $this->dedupeKey,
            'default_category_id' => $this->defaultCategoryId === null ? null : (string) $this->defaultCategoryId,
        ];
    }
}
