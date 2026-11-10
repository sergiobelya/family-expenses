<?php

namespace App\Catalog\Domain\Event;

final readonly class StoreAliasChanged extends CatalogEntityChanged
{
    protected function entityType(): string
    {
        return 'store_alias';
    }
}
