<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\UserId;

final class UserIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return UserId::class;
    }

    public function getName(): string
    {
        return 'user_id';
    }
}
