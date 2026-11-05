<?php

namespace App\Family\Application\Query;

use Symfony\Component\Uid\Uuid;

final readonly class GetFamilyInvitationsQuery
{
    public function __construct(
        public Uuid $familyId,
        public Uuid $requestedByUserId
    ) {
    }
}
