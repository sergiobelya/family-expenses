<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\FamilyMemberId;

final class FamilyMemberIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return FamilyMemberId::class;
    }

    public function getName(): string
    {
        return 'family_member_id';
    }
}
