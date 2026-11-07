<?php

namespace App\Catalog\Application\Query;

use App\Catalog\Application\CatalogAccess;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class SearchStoresHandler
{
    public function __construct(
        private CatalogAccess $access,
        private CatalogSearch $search
    ) {
    }

    public function __invoke(SearchStoresQuery $query): array
    {
        $this->access->assertMember($query->familyId, $query->requestedByUserId);

        return $this->search->stores($query->familyId, $query->query);
    }
}
