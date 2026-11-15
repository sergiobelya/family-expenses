<?php

namespace App\Catalog\Domain\Repository;

interface CatalogRepositoryInterface extends
    StoreCatalogRepositoryInterface,
    CategoryCatalogRepositoryInterface
{
}
