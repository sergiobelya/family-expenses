<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class CreateCategoryCommand
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $createdByUserId,
        public string $name
    ) {
    }
}
