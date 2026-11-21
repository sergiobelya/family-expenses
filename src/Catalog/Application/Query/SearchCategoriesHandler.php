<?php

namespace App\Catalog\Application\Query;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Category;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchCategoriesHandler
{
    public function __construct(
        private CatalogAccess $access,
        private CatalogSearchInterface $search
    ) {
    }

    /** @return Category[] */
    public function __invoke(SearchCategoriesQuery $query): array
    {
        $this->access->assertMember($query->familyId, $query->requestedByUserId);

        return $this->search->categories($query->familyId, $query->query);
    }
}
