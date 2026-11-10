<?php

namespace App\Core\Infrastructure\Outbox;

enum OutboxMessageStatus: string
{
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
}
