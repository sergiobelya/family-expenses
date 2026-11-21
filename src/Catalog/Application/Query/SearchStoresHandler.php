<?php

namespace App\Catalog\Application\Query;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Store;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchStoresHandler
{
    public function __construct(
        private CatalogAccess $access,
        private CatalogSearchInterface $search
    ) {
    }

    /** @return Store[] */
    public function __invoke(SearchStoresQuery $query): array
    {
        $this->access->assertMember($query->familyId, $query->requestedByUserId);

        return $this->search->stores($query->familyId, $query->query);
    }
}
