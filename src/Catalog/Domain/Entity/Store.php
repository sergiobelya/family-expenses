<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\StoreAliasCreated;
use App\Catalog\Domain\Event\StoreAliasDeleted;
use App\Catalog\Domain\Event\StoreAliasRenamed;
use App\Catalog\Domain\Event\StoreCreated;
use App\Catalog\Domain\Event\StoreDefaultCategoryChanged;
use App\Catalog\Domain\Event\StoreDeleted;
use App\Catalog\Domain\Event\StoreRenamed;
use App\Catalog\Domain\Exception\CatalogEntryNotFoundException;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreAliasId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

class Store extends AggregateRoot
{
    /** @var StoreAlias[] */
    private array $aliases = [];

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
        StoreName $name,
        UserId $createdByUserId,
        ?StoreId $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $store = new self(
            $familyId,
            $name,
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

    public function getName(): StoreName
    {
        return $this->name;
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

    public function rename(StoreName $storeName, UserId $actorUserId, ?DateTimeImmutable $now = null): void
    {
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
        foreach ($this->aliases as $alias) {
            $this->recordEvent(new StoreAliasDeleted(
                entityId: $alias->getId(),
                familyId: $this->getFamilyId(),
                actorUserId: $actorUserId,
                beforeData: $alias->snapshot(),
                occurredAt: $now
            ));
        }
        $this->aliases = [];
        $this->recordEvent(new StoreDeleted(
            entityId: $this->id,
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            beforeData: $this->snapshot(),
            occurredAt: $now
        ));
    }

    public function addAlias(StoreName $name, UserId $actorUserId, ?DateTimeImmutable $now = null): StoreAlias
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $storeAlias = StoreAlias::create(
            $this->familyId,
            $this,
            $name,
            now: $now
        );
        $this->aliases[] = $storeAlias;
        $this->recordEvent(new StoreAliasCreated(
            entityId: $storeAlias->getId(),
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            afterData: $storeAlias->snapshot(),
            occurredAt: $now
        ));

        return $storeAlias;
    }

    public function renameAlias(StoreAliasId $aliasId, StoreName $newName, UserId $actorUserId, ?DateTimeImmutable $now = null): StoreAlias
    {
        $alias = $this->findAlias($aliasId);
        if ((string) $alias->getName() === $newName->getValue() && $alias->getAliasDedupeKey() === $newName->getDedupeKey()) {
            return $alias;
        }

        $before = $alias->snapshot();
        $alias->setName($newName);
        $occurredAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordEvent(new StoreAliasRenamed(
            entityId: $alias->getId(),
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            beforeData: $before,
            afterData: $alias->snapshot(),
            occurredAt: $occurredAt
        ));

        return $alias;
    }

    public function removeAlias(StoreAliasId $aliasId, UserId $actorUserId, ?DateTimeImmutable $now = null): void
    {
        $alias = $this->findAlias($aliasId);
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->aliases = array_values(array_filter(
            $this->aliases,
            fn (StoreAlias $a) => !$a->getId()->equals($aliasId)
        ));
        $this->recordEvent(new StoreAliasDeleted(
            entityId: $alias->getId(),
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            beforeData: $alias->snapshot(),
            occurredAt: $now
        ));
    }

    public function hasAlias(StoreAliasId $aliasId): bool
    {
        foreach ($this->aliases as $alias) {
            if ($alias->getId()->equals($aliasId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return StoreAlias[]
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    private function findAlias(StoreAliasId $aliasId): StoreAlias
    {
        foreach ($this->aliases as $alias) {
            if ($alias->getId()->equals($aliasId)) {
                return $alias;
            }
        }

        throw CatalogEntryNotFoundException::forEntry('store alias', (string) $aliasId);
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
