<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\FamilyInvitations\CreateInvitationStep;
use App\Presentation\Telegram\Flow\FamilyInvitations\GetInvitationStatusLabel;
use App\Presentation\Telegram\Flow\FamilyInvitations\ListInvitationScreen;
use App\Presentation\Telegram\Flow\FamilyInvitations\RevokeInvitationStep;
use App\Presentation\Telegram\Flow\FamilyInvitations\ViewInvitationStep;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FamilyInvitationsFlowHandler implements FamilyContextFlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private InvitationRepositoryInterface $invitationRepository,
        private InvitationUseRepositoryInterface $invitationUseRepository,
        private ListInvitationScreen $listInvitationScreen,
        private GetInvitationStatusLabel $getStatusLabel,
        private TranslatorInterface $translator,
        #[Autowire('%app.telegram_bot_username%')]
        private string $botUsername,
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->callbackData === CallbackActions::FAMILY_INVITE_CREATE
            || $update->callbackData === CallbackActions::FAMILY_INVITATIONS_LIST) {
            return true;
        }

        if (str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_INVITATION_REVOKE_PREFIX)
            || str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_INVITATION_VIEW_PREFIX)
        ) {
            return true;
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        // Create invitation
        if ($update->callbackData === CallbackActions::FAMILY_INVITE_CREATE) {
            $step = new CreateInvitationStep(
                $this->messageBusDispatcher,
                $this->telegramClient,
                $this->translator,
                $this->botUsername,
            );
            $step->handle($update, $context);
            return;
        }

        // Revoke invitation
        if (str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_INVITATION_REVOKE_PREFIX)) {
            $step = new RevokeInvitationStep(
                $this->messageBusDispatcher,
                $this->listInvitationScreen,
                $this->telegramClient,
                $this->translator
            );
            $step->handle($update, $context);
            return;
        }

        // View invitation details
        if (str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_INVITATION_VIEW_PREFIX)) {
            $step = new ViewInvitationStep(
                $this->invitationRepository,
                $this->invitationUseRepository,
                $this->listInvitationScreen,
                $this->getStatusLabel,
                $this->telegramClient,
                $this->translator
            );
            $step->handle($update, $context);
            return;
        }

        // List invitations
        if ($update->callbackData === CallbackActions::FAMILY_INVITATIONS_LIST) {
            $this->listInvitationScreen->renderList($context);
        }
    }
}
