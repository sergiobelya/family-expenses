<?php

namespace App\Catalog\Domain\Repository;

interface CatalogRepositoryInterface extends
    FamilyCatalogLockInterface,
    StoreCatalogRepositoryInterface,
    CategoryCatalogRepositoryInterface
{
}
