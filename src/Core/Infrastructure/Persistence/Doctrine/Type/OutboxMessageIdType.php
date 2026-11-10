<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\OutboxMessageId;

final class OutboxMessageIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return OutboxMessageId::class;
    }

    public function getName(): string
    {
        return 'outbox_message_id';
    }
}
