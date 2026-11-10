<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\CategoryChanged;
use App\Catalog\Domain\ValueObject\CategoryName;
use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

class Category extends AggregateRoot
{
    private CategoryId $id;
    private CategoryName $name;
    private string $dedupeKey;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        private FamilyId $familyId,
        CategoryName $name,
        ?CategoryId $id,
        DateTimeImmutable $now
    ) {
        $this->id = $id ?? CategoryId::generate();
        $this->name = $name;
        $this->dedupeKey = $name->dedupeKey;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public static function create(
        FamilyId $familyId,
        string $name,
        ?UserId $createdByUserId = null,
        ?CategoryId $id = null,
        ?DateTimeImmutable $now = null
    ): self {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $category = new self(
            $familyId,
            CategoryName::fromString($name),
            $id,
            $now
        );
        $category->recordChange('category.created', null, $category->snapshot(), $createdByUserId, $now);

        return $category;
    }

    public function getId(): CategoryId
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
        $categoryName = CategoryName::fromString($name);
        if ($this->name->value === $categoryName->value && $this->dedupeKey === $categoryName->dedupeKey) {
            return;
        }

        $before = $this->snapshot();
        $this->name = $categoryName;
        $this->dedupeKey = $categoryName->dedupeKey;
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordChange('category.renamed', $before, $this->snapshot(), $actorUserId, $this->updatedAt);
    }

    public function delete(?UserId $actorUserId = null, ?DateTimeImmutable $now = null): void
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
            'name' => $this->name->value,
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
        ?UserId $actorUserId,
        DateTimeImmutable $occurredAt
    ): void {
        $this->recordEvent(new CategoryChanged(
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
