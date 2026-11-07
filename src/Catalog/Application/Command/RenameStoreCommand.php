<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class RenameStoreCommand
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $storeId,
        public Uuid $updatedByUserId,
        public string $name
    ) {
    }
}
