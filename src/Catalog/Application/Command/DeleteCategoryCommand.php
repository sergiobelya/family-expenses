<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteCategoryCommand
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $categoryId,
        public Uuid $deletedByUserId
    ) {
    }
}
