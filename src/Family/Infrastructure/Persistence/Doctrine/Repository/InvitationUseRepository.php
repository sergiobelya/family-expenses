<?php

namespace App\Family\Infrastructure\Persistence\Doctrine\Repository;

use App\Family\Domain\Entity\InvitationUse;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Core\Domain\ValueObject\FamilyId;
use App\Core\Domain\ValueObject\InvitationId;
use App\Core\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InvitationUseRepository implements InvitationUseRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function countByInvitationId(FamilyId $familyId, InvitationId $invitationId): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(invitation_use.id)
            FROM invitation_uses AS invitation_use
            INNER JOIN invitations AS invitation
                ON invitation.id = invitation_use.invitation_id
            WHERE invitation.family_id = :familyId
                AND invitation.id = :invitationId',
            [
                'familyId' => (string) $familyId,
                'invitationId' => (string) $invitationId,
            ]
        );
    }

    public function hasUserUsedInvitation(FamilyId $familyId, InvitationId $invitationId, UserId $userId): bool
    {
        return $this->entityManager->getConnection()->fetchOne(
            'SELECT 1
            FROM invitation_uses AS invitation_use
            INNER JOIN invitations AS invitation
                ON invitation.id = invitation_use.invitation_id
            WHERE invitation.family_id = :familyId
                AND invitation.id = :invitationId
                AND invitation_use.joined_user_id = :userId
            LIMIT 1',
            [
                'familyId' => (string) $familyId,
                'invitationId' => (string) $invitationId,
                'userId' => (string) $userId,
            ]
        ) !== false;
    }

    public function insertIfUnderLimit(FamilyId $familyId, InvitationUse $use): bool
    {
        $connection = $this->entityManager->getConnection();
        $invitationId = $use->getInvitationId();

        $affected = $connection->executeStatement(
            <<<'SQL'
            INSERT INTO invitation_uses (id, invitation_id, joined_user_id, used_at)
            SELECT :id, :invitationId, :joinedUserId, :usedAt
            FROM invitations
            WHERE invitations.id = :invitationId2
              AND invitations.family_id = :familyId
              AND (invitations.max_uses IS NULL OR (
                  SELECT COUNT(*)
                  FROM invitation_uses
                  WHERE invitation_uses.invitation_id = :invitationId3
              ) < invitations.max_uses)
            SQL,
            [
                'id' => (string) $use->getId(),
                'invitationId' => (string) $invitationId,
                'invitationId2' => (string) $invitationId,
                'invitationId3' => (string) $invitationId,
                'joinedUserId' => (string) $use->getJoinedUserId(),
                'usedAt' => $use->getUsedAt()->format(\DateTimeInterface::ATOM),
                'familyId' => (string) $familyId,
            ]
        );

        return $affected > 0;
    }
}
