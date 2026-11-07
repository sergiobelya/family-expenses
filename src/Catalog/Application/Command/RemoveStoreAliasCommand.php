<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class RemoveStoreAliasCommand
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $storeId,
        public Uuid $aliasId,
        public Uuid $deletedByUserId
    ) {
    }
}
