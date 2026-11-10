<?php

namespace App\Catalog\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreAliasId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;

final readonly class RenameStoreAliasCommand
{
    public function __construct(
        public FamilyId $familyId,
        public StoreId $storeId,
        public StoreAliasId $aliasId,
        public UserId $updatedByUserId,
        public string $alias
    ) {
    }
}
