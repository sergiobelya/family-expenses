<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\StoreId;

final class StoreIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return StoreId::class;
    }

    public function getName(): string
    {
        return 'store_id';
    }
}
