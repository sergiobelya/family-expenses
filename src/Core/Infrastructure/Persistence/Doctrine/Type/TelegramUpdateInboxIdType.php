<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\TelegramUpdateInboxId;

final class TelegramUpdateInboxIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return TelegramUpdateInboxId::class;
    }

    public function getName(): string
    {
        return 'telegram_update_inbox_id';
    }
}
