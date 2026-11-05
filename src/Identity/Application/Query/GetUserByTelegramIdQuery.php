<?php

namespace App\Identity\Application\Query;

final readonly class GetUserByTelegramIdQuery
{
    public function __construct(public int $telegramUserId)
    {
    }
}
