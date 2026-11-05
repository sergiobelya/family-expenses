<?php

namespace App\Family\Domain\Repository;

use App\Family\Domain\Entity\Invitation;
use Symfony\Component\Uid\Uuid;

interface InvitationRepositoryInterface
{
    public function save(Invitation $invitation): void;

    public function findById(Uuid $id): ?Invitation;

    public function findByTokenHash(string $tokenHash): ?Invitation;

    /**
     * @return Invitation[]
     */
    public function findByFamilyId(Uuid $familyId): array;
}
