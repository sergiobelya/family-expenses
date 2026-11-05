<?php

namespace App\Family\Application\Query;

use Symfony\Component\Uid\Uuid;

final readonly class GetUserFamiliesQuery
{
    public function __construct(public Uuid $userId)
    {
    }
}
