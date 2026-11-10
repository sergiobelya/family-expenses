<?php

namespace App\Catalog\Domain\Event;

final readonly class StoreChanged extends CatalogEntityChanged
{
    protected function entityType(): string
    {
        return 'store';
    }
}
