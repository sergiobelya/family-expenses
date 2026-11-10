<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\AuditLogId;

final class AuditLogIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return AuditLogId::class;
    }

    public function getName(): string
    {
        return 'audit_log_id';
    }
}
