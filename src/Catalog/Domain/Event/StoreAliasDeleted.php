<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\ValueObject\{
    FamilyId,
    StoreAliasId,
    UserId
};

final readonly class StoreAliasDeleted extends StoreAliasChanged
{
    public function __construct(
        StoreAliasId $entityId,
        FamilyId $familyId,
        UserId $actorUserId,
        array $beforeData,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct(
            action: 'store_alias.deleted',
            entityId: $entityId,
            familyId: $familyId,
            actorUserId: $actorUserId,
            beforeData: $beforeData,
            afterData: null,
            occurredAt: $occurredAt
        );
    }
}
