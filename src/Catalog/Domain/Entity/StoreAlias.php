<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ValueObject\StoreName;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreAliasId;
use App\Core\Domain\ValueObject\StoreId;
use DateTimeImmutable;

/**
 * StoreAlias is an entity within the Store aggregate.
 * It is not an aggregate root — lifecycle methods are invoked by the Store root.
 * @see Store
 */
final class StoreAlias
{
    private StoreAliasId $id;
    private StoreName $alias;
    private string $aliasDedupeKey;
    private DateTimeImmutable $createdAt;

    private function __construct(
        private FamilyId $familyId,
        private Store $store,
        StoreName $name,
        ?StoreAliasId $id,
        DateTimeImmutable $now
    ) {
        $this->id = $id ?? StoreAliasId::generate();
        $this->alias = $name;
        $this->aliasDedupeKey = $name->getDedupeKey();
        $this->createdAt = $now;
    }

    public static function create(
        FamilyId $familyId,
        Store $store,
        StoreName $name,
        ?StoreAliasId $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $now ??= new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return new self(
            $familyId,
            $store,
            $name,
            $id,
            $now
        );
    }

    public function getId(): StoreAliasId
    {
        return $this->id;
    }

    public function getFamilyId(): FamilyId
    {
        return $this->familyId;
    }

    public function getStore(): Store
    {
        return $this->store;
    }

    public function setName(StoreName $name): void
    {
        $this->alias = $name;
        $this->aliasDedupeKey = $name->getDedupeKey();
    }

    public function getStoreId(): StoreId
    {
        return $this->store->getId();
    }

    public function getName(): StoreName
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

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'family_id' => (string) $this->familyId,
            'store_id' => (string) $this->store->getId(),
            'alias' => $this->alias->getValue(),
            'alias_dedupe_key' => $this->aliasDedupeKey,
        ];
    }
}
