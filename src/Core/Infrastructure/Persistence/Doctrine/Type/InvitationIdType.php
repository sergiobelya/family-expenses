<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\InvitationId;

final class InvitationIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return InvitationId::class;
    }

    public function getName(): string
    {
        return 'invitation_id';
    }
}
