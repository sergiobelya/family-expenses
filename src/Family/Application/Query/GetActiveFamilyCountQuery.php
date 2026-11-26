<?php

namespace App\Family\Application\Query;

use App\Core\Domain\ValueObject\UserId;

final readonly class GetActiveFamilyCountQuery
{
    public function __construct(public UserId $userId)
    {
    }
}
