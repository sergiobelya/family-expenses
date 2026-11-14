<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\ValueObject\{
    FamilyId,
    StoreId,
    UserId
};

final readonly class StoreDeleted extends StoreChanged
{
    public function __construct(
        StoreId $entityId,
        FamilyId $familyId,
        UserId $actorUserId,
        array $beforeData,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct(
            action: 'store.deleted',
            entityId: $entityId,
            familyId: $familyId,
            actorUserId: $actorUserId,
            beforeData: $beforeData,
            afterData: null,
            occurredAt: $occurredAt
        );
    }
}
