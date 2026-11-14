<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\ValueObject\{
    FamilyId,
    StoreId,
    UserId
};

final readonly class StoreCreated extends StoreChanged
{
    public function __construct(
        StoreId $entityId,
        FamilyId $familyId,
        UserId $actorUserId,
        array $afterData,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct(
            action: 'store.created',
            entityId: $entityId,
            familyId: $familyId,
            actorUserId: $actorUserId,
            beforeData: null,
            afterData: $afterData,
            occurredAt: $occurredAt
        );
    }
}
