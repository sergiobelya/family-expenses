<?php

namespace App\Catalog\Application\Query;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Repository\CatalogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListStoresHandler
{
    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    public function __invoke(ListStoresQuery $query): array
    {
        $this->access->assertMember($query->familyId, $query->requestedByUserId);

        return $this->catalog->findStores($query->familyId);
    }
}
