<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class RenameStoreAliasCommand
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $storeId,
        public Uuid $aliasId,
        public Uuid $updatedByUserId,
        public string $alias
    ) {
    }
}
