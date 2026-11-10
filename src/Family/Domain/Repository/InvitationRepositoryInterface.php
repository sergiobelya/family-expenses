<?php

namespace App\Family\Domain\Repository;

use App\Family\Domain\Entity\Invitation;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\InvitationId;

interface InvitationRepositoryInterface
{
    public function save(Invitation $invitation): void;

    public function findById(InvitationId $id): ?Invitation;

    public function findByTokenHash(string $tokenHash): ?Invitation;

    /**
     * @return Invitation[]
     */
    public function findByFamilyId(FamilyId $familyId): array;
}
