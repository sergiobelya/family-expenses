<?php

namespace App\Catalog\Application\Query;

use App\Catalog\Application\CatalogAccess;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Repository\CategoryCatalogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListCategoriesHandler
{
    public function __construct(
        private CategoryCatalogRepositoryInterface $catalog,
        private CatalogAccess $access
    ) {
    }

    /** @return list<Category> */
    public function __invoke(ListCategoriesQuery $query): array
    {
        $this->access->assertMember($query->familyId, $query->requestedByUserId);

        return $this->catalog->findCategories($query->familyId);
    }
}
