<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\ValueObject\{
    FamilyId,
    StoreAliasId,
    UserId
};

final readonly class StoreAliasCreated extends StoreAliasChanged
{
    public function __construct(
        StoreAliasId $entityId,
        FamilyId $familyId,
        UserId $actorUserId,
        array $afterData,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct(
            action: 'store_alias.created',
            entityId: $entityId,
            familyId: $familyId,
            actorUserId: $actorUserId,
            beforeData: null,
            afterData: $afterData,
            occurredAt: $occurredAt
        );
    }
}
