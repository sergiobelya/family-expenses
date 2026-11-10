<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\FamilyId;

final class FamilyIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return FamilyId::class;
    }

    public function getName(): string
    {
        return 'family_id';
    }
}
