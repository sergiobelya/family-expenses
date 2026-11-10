<?php

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ValueObject\CategoryName;

final class CategoryNameType extends CatalogNameType
{
    protected function getNameClass(): string
    {
        return CategoryName::class;
    }

    public function getName(): string
    {
        return 'category_name';
    }
}
