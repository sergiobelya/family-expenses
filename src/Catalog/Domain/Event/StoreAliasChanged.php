<?php

namespace App\Catalog\Domain\Event;

readonly class StoreAliasChanged extends CatalogEntityChanged
{
    protected function entityType(): string
    {
        return 'store_alias';
    }
}
