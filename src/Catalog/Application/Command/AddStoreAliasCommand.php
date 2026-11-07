<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class AddStoreAliasCommand
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $storeId,
        public Uuid $createdByUserId,
        public string $alias
    ) {
    }
}
