<?php

namespace App\Catalog\Domain\Event;

use App\Core\Domain\ValueObject\StoreAliasId;

final readonly class StoreAliasRenamed extends StoreAliasChanged
{
    public function __construct(
        StoreAliasId $entityId,
        \App\Core\Domain\ValueObject\FamilyId $familyId,
        \App\Core\Domain\ValueObject\UserId $actorUserId,
        array $beforeData,
        array $afterData,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct(
            action: 'store_alias.renamed',
            entityId: $entityId,
            familyId: $familyId,
            actorUserId: $actorUserId,
            beforeData: $beforeData,
            afterData: $afterData,
            occurredAt: $occurredAt
        );
    }
}
