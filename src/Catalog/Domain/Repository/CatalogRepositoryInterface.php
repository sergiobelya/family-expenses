<?php

namespace App\Catalog\Domain\Repository;

use App\Catalog\Application\FamilyCatalogLockInterface;

interface CatalogRepositoryInterface extends
    FamilyCatalogLockInterface,
    StoreCatalogRepositoryInterface,
    CategoryCatalogRepositoryInterface
{
}
