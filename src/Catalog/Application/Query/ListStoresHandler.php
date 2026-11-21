<?php

namespace App\Catalog\Application\Query;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Store;
use App\Catalog\Domain\Repository\StoreCatalogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListStoresHandler
{
    public function __construct(
        private StoreCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    /** @return Store[] */
    public function __invoke(ListStoresQuery $query): array
    {
        $this->access->assertMember($query->familyId, $query->requestedByUserId);

        return $this->catalog->findStores($query->familyId);
    }
}
