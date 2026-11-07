<?php

namespace App\Catalog\Application\Query;

use App\Catalog\Application\CatalogAccess;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class SearchCategoriesHandler
{
    public function __construct(
        private CatalogAccess $access,
        private CatalogSearch $search
    ) {
    }

    public function __invoke(SearchCategoriesQuery $query): array
    {
        $this->access->assertMember($query->familyId, $query->requestedByUserId);

        return $this->search->categories($query->familyId, $query->query);
    }
}
