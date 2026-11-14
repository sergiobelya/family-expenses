<?php

namespace App\Catalog\Domain\Event;

readonly class CategoryChanged extends CatalogEntityChanged
{
    protected function entityType(): string
    {
        return 'category';
    }
}
