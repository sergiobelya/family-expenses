<?php

namespace App\Presentation\Telegram\Flow\FamilyInvitations;

use App\Core\Domain\ValueObject\InvitationId;
use App\Family\Application\Command\RevokeInvitationCommand;
use App\Family\Application\Command\RevokeInvitationHandler;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RevokeInvitationStep implements FamilyInvitationsStepInterface
{
    public function __construct(
        private RevokeInvitationHandler $revokeInvitationHandler,
        private ListInvitationScreen $listInvitationScreen,
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context
    ): void {
        $activeFamily = $context->activeFamily;

        $invitationId = InvitationId::fromString(substr($update->callbackData ?? '', strlen(CallbackActions::FAMILY_INVITATION_REVOKE_PREFIX)));

        ($this->revokeInvitationHandler)(new RevokeInvitationCommand(
            familyId: $activeFamily->getId(),
            invitationId: $invitationId,
            revokedByUserId: $context->user->getId()
        ));

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('family.invite.revoked_success')
        );

        $this->listInvitationScreen->renderList($context);
    }
}
