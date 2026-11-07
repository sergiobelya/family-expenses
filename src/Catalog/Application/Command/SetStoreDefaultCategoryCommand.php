<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class SetStoreDefaultCategoryCommand
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $storeId,
        public ?Uuid $categoryId,
        public Uuid $updatedByUserId
    ) {
    }
}
