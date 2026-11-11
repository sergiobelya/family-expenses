<?php

namespace App\Family\Domain\Enum;

enum FamilyMemberStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
