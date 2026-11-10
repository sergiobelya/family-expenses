<?php

namespace App\Catalog\Application\Command;

use App\Core\Domain\ValueObject\CategoryId;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\StoreId;
use App\Core\Domain\ValueObject\UserId;

final readonly class SetStoreDefaultCategoryCommand
{
    public function __construct(
        public FamilyId $familyId,
        public StoreId $storeId,
        public ?CategoryId $categoryId,
        public UserId $updatedByUserId
    ) {
    }
}
