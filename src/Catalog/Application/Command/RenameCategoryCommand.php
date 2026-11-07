<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class RenameCategoryCommand
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $categoryId,
        public Uuid $updatedByUserId,
        public string $name
    ) {
    }
}
