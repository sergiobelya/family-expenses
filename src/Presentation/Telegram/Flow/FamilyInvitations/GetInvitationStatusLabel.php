<?php

declare(strict_types=1);

namespace App\Presentation\Telegram\Flow\FamilyInvitations;

use App\Family\Domain\Entity\Invitation;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class GetInvitationStatusLabel
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    public function __invoke(Invitation $inv, int $usesCount): string
    {
        if ($inv->isRevoked()) {
            return $this->translator->trans('family.invite.status_revoked');
        }
        if ($inv->isExpired()) {
            return $this->translator->trans('family.invite.status_expired');
        }
        if ($inv->getMaxUses() !== null && $usesCount >= $inv->getMaxUses()) {
            return $this->translator->trans('family.invite.status_fully_used');
        }
        return $this->translator->trans('family.invite.status_active');
    }
}
