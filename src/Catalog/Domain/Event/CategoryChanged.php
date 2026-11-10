<?php

namespace App\Catalog\Domain\Event;

final readonly class CategoryChanged extends CatalogEntityChanged
{
    protected function entityType(): string
    {
        return 'category';
    }
}
