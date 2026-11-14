<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\ValueObject\CategoryId;

final readonly class CategoryDeleted extends CategoryChanged
{
    public function __construct(
        CategoryId $entityId,
        \App\Core\Domain\ValueObject\FamilyId $familyId,
        \App\Core\Domain\ValueObject\UserId $actorUserId,
        array $beforeData,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct(
            action: 'category.deleted',
            entityId: $entityId,
            familyId: $familyId,
            actorUserId: $actorUserId,
            beforeData: $beforeData,
            afterData: null,
            occurredAt: $occurredAt
        );
    }
}
