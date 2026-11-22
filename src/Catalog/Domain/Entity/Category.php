<?php

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryDeleted;
use App\Catalog\Domain\Event\CategoryRenamed;
use App\Catalog\Domain\ValueObject\CategoryName;
use App\Core\Domain\Aggregate\AggregateRoot;
use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

final class Category extends AggregateRoot
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
        $this->dedupeKey = $name->getDedupeKey();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public static function create(
        FamilyId $familyId,
        string $name,
        UserId $createdByUserId,
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
        $category->recordEvent(new CategoryCreated(
            entityId: $category->getId(),
            familyId: $category->getFamilyId(),
            actorUserId: $createdByUserId,
            afterData: $category->snapshot(),
            occurredAt: $now
        ));

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

    public function getName(): CategoryName
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

    public function rename(CategoryName $categoryName, UserId $actorUserId, ?DateTimeImmutable $now = null): void
    {
        if (!$this->nameIsDifferentFrom($categoryName)) {
            return;
        }

        $before = $this->snapshot();
        $this->name = $categoryName;
        $this->dedupeKey = $categoryName->getDedupeKey();
        $this->updatedAt = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->recordEvent(new CategoryRenamed(
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
        $this->recordEvent(new CategoryDeleted(
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
        ];
    }

    private function nameIsDifferentFrom(CategoryName $categoryName): bool
    {
        return $this->name->getValue() !== $categoryName->getValue()
            || $this->dedupeKey !== $categoryName->getDedupeKey();
    }
}
