<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\StoreAliasId;

final class StoreAliasIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return StoreAliasId::class;
    }

    public function getName(): string
    {
        return 'store_alias_id';
    }
}
