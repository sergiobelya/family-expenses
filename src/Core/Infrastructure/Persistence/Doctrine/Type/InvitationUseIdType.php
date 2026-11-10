<?php

namespace App\Core\Infrastructure\Persistence\Doctrine\Type;

use App\Core\Domain\ValueObject\InvitationUseId;

final class InvitationUseIdType extends UuidValueObjectType
{
    protected function getValueObjectClass(): string
    {
        return InvitationUseId::class;
    }

    public function getName(): string
    {
        return 'invitation_use_id';
    }
}
