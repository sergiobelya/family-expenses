<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\StoreAliasChanged;
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
        ?UserId $createdByUserId = null,
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
        $storeAlias->recordChange('store_alias.created', null, $storeAlias->snapshot(), $createdByUserId, $now);

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
        return $this->alias->value;
    }

    public function getAliasDedupeKey(): string
    {
        return $this->aliasDedupeKey;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $alias, ?UserId $actorUserId = null, ?DateTimeImmutable $now = null): void
    {
        $storeName = StoreName::fromString($alias);
        if ($this->alias->value === $storeName->value && $this->aliasDedupeKey === $storeName->dedupeKey) {
            return;
        }

        $before = $this->snapshot();
        $this->alias = $storeName;
        $this->aliasDedupeKey = $storeName->dedupeKey;
        $this->recordChange(
            'store_alias.renamed',
            $before,
            $this->snapshot(),
            $actorUserId,
            $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }

    public function delete(?UserId $actorUserId = null, ?DateTimeImmutable $now = null): void
    {
        $this->recordChange(
            'store_alias.deleted',
            $this->snapshot(),
            null,
            $actorUserId,
            $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'family_id' => (string) $this->familyId,
            'store_id' => (string) $this->storeId,
            'alias' => $this->alias->value,
            'alias_dedupe_key' => $this->aliasDedupeKey,
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
        $this->recordEvent(new StoreAliasChanged(
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
