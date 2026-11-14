<?php

namespace App\Catalog\Domain\Event;

readonly class StoreChanged extends CatalogEntityChanged
{
    protected function entityType(): string
    {
        return 'store';
    }
}
