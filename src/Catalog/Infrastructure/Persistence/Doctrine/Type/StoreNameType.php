<?php

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ValueObject\StoreName;

final class StoreNameType extends CatalogNameType
{
    protected function getNameClass(): string
    {
        return StoreName::class;
    }

    public function getName(): string
    {
        return 'store_name';
    }
}
