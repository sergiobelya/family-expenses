<?php

namespace App\Catalog\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;

final readonly class AddStoreAliasCommand
{
    public function __construct(
        public FamilyId $familyId,
        public StoreId $storeId,
        public UserId $createdByUserId,
        public string $alias
    ) {
    }
}
