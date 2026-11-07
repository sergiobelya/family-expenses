<?php

namespace App\Catalog\Application\Query;

use Symfony\Component\Uid\Uuid;

final readonly class ListStoresQuery
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $requestedByUserId
    ) {
    }
}
