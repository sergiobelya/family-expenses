<?php

namespace App\Family\Domain\Repository;

use App\Family\Domain\Entity\Invitation;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\InvitationId;

interface InvitationRepositoryInterface
{
    public function save(Invitation $invitation): void;

    public function findById(FamilyId $familyId, InvitationId $id): ?Invitation;

    /**
     * Global lookup by a unique token; used before the invitation's family is known.
     */
    public function findByTokenHash(string $tokenHash): ?Invitation;

    /**
     * @return Invitation[]
     */
    public function findByFamilyId(FamilyId $familyId): array;
}
