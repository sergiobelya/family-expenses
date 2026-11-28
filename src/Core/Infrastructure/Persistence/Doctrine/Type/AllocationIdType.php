<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\AllocationId;

final class AllocationIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return AllocationId::class;
    }

    public function getName(): string
    {
        return 'allocation_id';
    }
}
