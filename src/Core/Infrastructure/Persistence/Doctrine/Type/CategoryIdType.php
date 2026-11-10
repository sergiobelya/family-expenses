<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\CategoryId;

final class CategoryIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return CategoryId::class;
    }

    public function getName(): string
    {
        return 'category_id';
    }
}
