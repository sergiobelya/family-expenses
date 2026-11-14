<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\ValueObject\{
    FamilyId,
    StoreId,
    UserId
};

final readonly class StoreRenamed extends StoreChanged
{
    public function __construct(
        StoreId $entityId,
        FamilyId $familyId,
        UserId $actorUserId,
        array $beforeData,
        array $afterData,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct(
            action: 'store.renamed',
            entityId: $entityId,
            familyId: $familyId,
            actorUserId: $actorUserId,
            beforeData: $beforeData,
            afterData: $afterData,
            occurredAt: $occurredAt
        );
    }
}
