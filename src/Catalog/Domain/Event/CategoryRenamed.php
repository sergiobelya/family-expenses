<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\ValueObject\CategoryId;

final readonly class CategoryRenamed extends CategoryChanged
{
    public function __construct(
        CategoryId $entityId,
        \App\Core\Domain\ValueObject\FamilyId $familyId,
        \App\Core\Domain\ValueObject\UserId $actorUserId,
        array $beforeData,
        array $afterData,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct(
            action: 'category.renamed',
            entityId: $entityId,
            familyId: $familyId,
            actorUserId: $actorUserId,
            beforeData: $beforeData,
            afterData: $afterData,
            occurredAt: $occurredAt
        );
    }
}
