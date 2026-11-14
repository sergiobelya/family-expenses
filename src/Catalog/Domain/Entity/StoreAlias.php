<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\StoreAliasCreated;
use App\Catalog\Domain\Event\StoreAliasDeleted;
use App\Catalog\Domain\Event\StoreAliasRenamed;
use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreAliasId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

class StoreAlias extends AggregateRoot
{
    private StoreAliasId $id;
    private StoreName $alias;
    private string $aliasDedupeKey;
    private DateTimeImmutable $createdAt;

    private function __construct(
        private FamilyId $familyId,
        private StoreId $storeId,
        StoreName $name,
        ?StoreAliasId $id,
        DateTimeImmutable $now
    ) {
        $this->id = $id ?? StoreAliasId::generate();
        $this->alias = $name;
        $this->aliasDedupeKey = $name->dedupeKey;
        $this->createdAt = $now;
    }

    public static function create(
        FamilyId $familyId,
        StoreId $storeId,
        string $alias,
        UserId $createdByUserId,
        ?StoreAliasId $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $storeAlias = new self(
            $familyId,
            $storeId,
            StoreName::fromString($alias),
            $id,
            $now
        );
        $storeAlias->recordEvent(new StoreAliasCreated(
            entityId: $storeAlias->getId(),
            familyId: $storeAlias->getFamilyId(),
            actorUserId: $createdByUserId,
            afterData: $storeAlias->snapshot(),
            occurredAt: $now
        ));

        return $storeAlias;
    }

    public function getId(): StoreAliasId
    {
        return $this->id;
    }

    public function getFamilyId(): FamilyId
    {
        return $this->familyId;
    }

    public function getStoreId(): StoreId
    {
        return $this->storeId;
    }

    public function getAlias(): string
    {
        return (string) $this->alias;
    }

    public function getAliasDedupeKey(): string
    {
        return $this->aliasDedupeKey;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $alias, UserId $actorUserId, ?DateTimeImmutable $now = null): void
    {
        $storeName = StoreName::fromString($alias);
        if ((string) $this->alias === (string) $storeName && $this->aliasDedupeKey === $storeName->dedupeKey) {
            return;
        }

        $before = $this->snapshot();
        $this->alias = $storeName;
        $this->aliasDedupeKey = $storeName->dedupeKey;
        $occurredAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordEvent(new StoreAliasRenamed(
            entityId: $this->id,
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            beforeData: $before,
            afterData: $this->snapshot(),
            occurredAt: $occurredAt
        ));
    }

    public function delete(UserId $actorUserId, ?DateTimeImmutable $now = null): void
    {
        $occurredAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordEvent(new StoreAliasDeleted(
            entityId: $this->id,
            familyId: $this->getFamilyId(),
            actorUserId: $actorUserId,
            beforeData: $this->snapshot(),
            occurredAt: $occurredAt
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'family_id' => (string) $this->familyId,
            'store_id' => (string) $this->storeId,
            'alias' => (string) $this->alias,
            'alias_dedupe_key' => $this->aliasDedupeKey,
        ];
    }
}
