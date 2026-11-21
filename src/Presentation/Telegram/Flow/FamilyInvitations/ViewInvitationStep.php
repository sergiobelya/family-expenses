<?php

namespace App\Presentation\Telegram\Flow\FamilyInvitations;

use App\Core\Domain\ValueObject\InvitationId;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ViewInvitationStep implements FamilyInvitationsStepInterface
{
    public function __construct(
        private InvitationRepositoryInterface $invitationRepository,
        private InvitationUseRepositoryInterface $invitationUseRepository,
        private ListInvitationScreen $listInvitationScreen,
        private GetInvitationStatusLabel $getStatusLabel,
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context
    ): void {
        $activeFamily = $context->activeFamily;

        $invitationId = InvitationId::fromString(substr($update->callbackData ?? '', strlen(CallbackActions::FAMILY_INVITATION_VIEW_PREFIX)));
        $invitation = $this->invitationRepository->findById($activeFamily->getId(), $invitationId);

        if ($invitation === null || !$invitation->getFamilyId()->equals($activeFamily->getId())) {
            $this->listInvitationScreen->renderList($context);
            return;
        }

        $usesCount = $this->invitationUseRepository->countByInvitationId($activeFamily->getId(), $invitation->getId());
        $status = ($this->getStatusLabel)($invitation, $usesCount);

        $details = $this->translator->trans('family.invite.details_title', [
            '%{status}' => $status,
            '%{uses}' => $usesCount,
            '%{max_uses}' => $invitation->getMaxUses() ?? '∞',
            '%{expires_at}' => $invitation->getExpiresAt()->format('Y-m-d H:i'),
        ]);

        $builder = new InlineKeyboardBuilder();
        if ($invitation->isActive() && ($invitation->getMaxUses() === null || $usesCount < $invitation->getMaxUses())) {
            $builder->button($this->translator->trans('family.invite.revoke_btn'), CallbackActions::FAMILY_INVITATION_REVOKE_PREFIX . $invitation->getId()->toRfc4122())->row();
        }
        $builder->button($this->translator->trans('common.back'), CallbackActions::FAMILY_INVITATIONS_LIST)->row();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $details,
            replyMarkup: $builder->toArray()
        );
    }
}
