<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\CatalogChanged;
use App\Catalog\Domain\ValueObject\CatalogName;
use App\Core\Domain\Aggregate\AggregateRoot;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

class Category extends AggregateRoot
{
    private Uuid $id;
    private string $name;
    private string $dedupeKey;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        private Uuid $familyId,
        CatalogName $name,
        ?Uuid $id,
        DateTimeImmutable $now
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->name = $name->value;
        $this->dedupeKey = $name->dedupeKey;
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
        $category = new self($familyId, CatalogName::fromString($name), $id, $now);
        $category->recordChange('category.created', null, $category->snapshot(), $createdByUserId, $now);

        return $category;
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
        $this->recordChange('category.renamed', $before, $this->snapshot(), $actorUserId, $this->updatedAt);
    }

    public function delete(?Uuid $actorUserId = null, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordChange('category.deleted', $this->snapshot(), null, $actorUserId, $now);
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
            entityType: 'category',
            entityId: (string) $this->id,
            familyId: $this->familyId,
            actorUserId: $actorUserId,
            beforeData: $before,
            afterData: $after,
            occurredAt: $occurredAt
        ));
    }
}
