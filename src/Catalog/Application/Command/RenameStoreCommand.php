<?php

namespace App\Catalog\Application\Command;

use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;

final readonly class RenameStoreCommand
{
    public function __construct(
        public FamilyId $familyId,
        public StoreId $storeId,
        public UserId $updatedByUserId,
        public string $name
    ) {
    }
}
