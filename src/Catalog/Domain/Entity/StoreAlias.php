<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\CatalogChanged;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Core\Domain\Aggregate\AggregateRoot;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

class StoreAlias extends AggregateRoot
{
    private Uuid $id;
    private string $alias;
    private string $aliasDedupeKey;
    private DateTimeImmutable $createdAt;

    private function __construct(
        private Uuid $familyId,
        private Uuid $storeId,
        CatalogName $name,
        ?Uuid $id,
        DateTimeImmutable $now
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->alias = $name->value;
        $this->aliasDedupeKey = $name->dedupeKey;
        $this->createdAt = $now;
    }

    public static function create(
        Uuid $familyId,
        Uuid $storeId,
        string $alias,
        ?Uuid $createdByUserId = null,
        ?Uuid $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $storeAlias = new self($familyId, $storeId, CatalogName::fromString($alias), $id, $now);
        $storeAlias->recordChange('store_alias.created', null, $storeAlias->snapshot(), $createdByUserId, $now);

        return $storeAlias;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFamilyId(): Uuid
    {
        return $this->familyId;
    }

    public function getStoreId(): Uuid
    {
        return $this->storeId;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getAliasDedupeKey(): string
    {
        return $this->aliasDedupeKey;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $alias, ?Uuid $actorUserId = null, ?DateTimeImmutable $now = null): void
    {
        $catalogName = CatalogName::fromString($alias);
        if ($this->alias === $catalogName->value && $this->aliasDedupeKey === $catalogName->dedupeKey) {
            return;
        }

        $before = $this->snapshot();
        $this->alias = $catalogName->value;
        $this->aliasDedupeKey = $catalogName->dedupeKey;
        $this->recordChange(
            'store_alias.renamed',
            $before,
            $this->snapshot(),
            $actorUserId,
            $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }

    public function delete(?Uuid $actorUserId = null, ?DateTimeImmutable $now = null): void
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
            'alias' => $this->alias,
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
        ?Uuid $actorUserId,
        DateTimeImmutable $occurredAt
    ): void {
        $this->recordEvent(new CatalogChanged(
            action: $action,
            entityType: 'store_alias',
            entityId: (string) $this->id,
            familyId: $this->familyId,
            actorUserId: $actorUserId,
            beforeData: $before,
            afterData: $after,
            occurredAt: $occurredAt
        ));
    }
}
