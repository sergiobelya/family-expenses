<?php

namespace App\Presentation\Telegram\Flow;

use App\Family\Application\Command\CreateInvitationCommand;
use App\Family\Application\Command\CreateInvitationHandler;
use App\Family\Application\Command\RevokeInvitationCommand;
use App\Family\Application\Command\RevokeInvitationHandler;
use App\Family\Application\Query\GetFamilyInvitationsHandler;
use App\Family\Application\Query\GetFamilyInvitationsQuery;
use App\Family\Domain\Entity\Invitation;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class FamilyInvitationsFlowHandler implements FlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private CreateInvitationHandler $createInvitationHandler,
        private RevokeInvitationHandler $revokeInvitationHandler,
        private GetFamilyInvitationsHandler $getFamilyInvitationsHandler,
        private InvitationRepositoryInterface $invitationRepository,
        private InvitationUseRepositoryInterface $invitationUseRepository,
        private TranslatorInterface $translator,
        #[Autowire('%app.telegram_bot_username%')]
        private string $botUsername
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if (!$context->hasActiveFamily()) {
            return false;
        }

        if ($update->callbackData === 'family:invite:create' || $update->callbackData === 'family:invitations:list') {
            return true;
        }

        if (str_starts_with($update->callbackData ?? '', 'family:invitation:')) {
            return true;
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramUserContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        $activeFamily = $context->activeFamily;
        if ($activeFamily === null) {
            return;
        }

        // 1. Create Invitation
        if ($update->callbackData === 'family:invite:create') {
            $result = ($this->createInvitationHandler)(new CreateInvitationCommand(
                familyId: $activeFamily->getId(),
                userId: $context->user->getId(),
                expiresInHours: 48,
                maxUses: 1
            ));

            $plainToken = $result['plain_token'];
            /** @var Invitation $invitation */
            $invitation = $result['invitation'];

            $deepLink = sprintf('https://t.me/%s?start=invite_%s', $this->botUsername, $plainToken);

            $msg = $this->translator->trans('family.invite.create_success', [
                '%{family_name}' => htmlspecialchars($activeFamily->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                '%{link}' => $deepLink,
                '%{max_uses}' => $invitation->getMaxUses() ?? '∞',
            ]);

            $kb = (new InlineKeyboardBuilder())
                ->button($this->translator->trans('family.members.invitations_btn'), 'family:invitations:list')
                ->button($this->translator->trans('common.main_menu'), 'nav:main_menu')
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $msg,
                replyMarkup: $kb
            );
            return;
        }

        // 2. Revoke Invitation
        if (str_starts_with($update->callbackData ?? '', 'family:invitation:revoke:')) {
            $invitationIdStr = substr($update->callbackData, strlen('family:invitation:revoke:'));
            $invitationId = Uuid::fromString($invitationIdStr);

            ($this->revokeInvitationHandler)(new RevokeInvitationCommand(
                invitationId: $invitationId,
                revokedByUserId: $context->user->getId()
            ));

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.invite.revoked_success')
            );

            // Re-render list
            $this->renderList($context, $activeFamily);
            return;
        }

        // 3. View Invitation Details
        if (str_starts_with($update->callbackData ?? '', 'family:invitation:view:')) {
            $invitationIdStr = substr($update->callbackData, strlen('family:invitation:view:'));
            $invitation = $this->invitationRepository->findById(Uuid::fromString($invitationIdStr));

            if ($invitation === null || !$invitation->getFamilyId()->equals($activeFamily->getId())) {
                $this->renderList($context, $activeFamily);
                return;
            }

            $usesCount = $this->invitationUseRepository->countByInvitationId($invitation->getId());
            $status = $this->getStatusLabel($invitation, $usesCount);

            $details = $this->translator->trans('family.invite.details_title', [
                '%{status}' => $status,
                '%{uses}' => $usesCount,
                '%{max_uses}' => $invitation->getMaxUses() ?? '∞',
                '%{expires_at}' => $invitation->getExpiresAt()->format('Y-m-d H:i'),
            ]);

            $builder = new InlineKeyboardBuilder();
            if ($invitation->isActive() && ($invitation->getMaxUses() === null || $usesCount < $invitation->getMaxUses())) {
                $builder->button($this->translator->trans('family.invite.revoke_btn'), 'family:invitation:revoke:' . $invitation->getId()->toRfc4122())->row();
            }
            $builder->button($this->translator->trans('common.back'), 'family:invitations:list')->row();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $details,
                replyMarkup: $builder->toArray()
            );
            return;
        }

        // 4. List Invitations
        if ($update->callbackData === 'family:invitations:list') {
            $this->renderList($context, $activeFamily);
        }
    }

    private function renderList(TelegramUserContext $context, $activeFamily): void
    {
        $invitations = ($this->getFamilyInvitationsHandler)(new GetFamilyInvitationsQuery($activeFamily->getId(), $context->user->getId()));

        $builder = new InlineKeyboardBuilder();
        $builder->button($this->translator->trans('family.members.invite_btn'), 'family:invite:create')->row();

        if (empty($invitations)) {
            $builder->button($this->translator->trans('common.back'), 'family:members')->row();
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.invite.empty_list'),
                replyMarkup: $builder->toArray()
            );
            return;
        }

        $lines = [
            $this->translator->trans('family.invite.list_title', [
                '%{family_name}' => htmlspecialchars($activeFamily->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ]),
            '',
        ];

        foreach ($invitations as $inv) {
            $usesCount = $this->invitationUseRepository->countByInvitationId($inv->getId());
            $status = $this->getStatusLabel($inv, $usesCount);

            $idShort = substr($inv->getId()->toRfc4122(), 0, 8);
            $lines[] = $this->translator->trans('family.invite.item', [
                '%{id}' => $idShort,
                '%{status}' => $status,
                '%{uses}' => $usesCount,
                '%{max_uses}' => $inv->getMaxUses() ?? '∞',
                '%{expires_at}' => $inv->getExpiresAt()->format('m-d H:i'),
            ]);

            $builder->button(sprintf('#%s (%s)', $idShort, $status), 'family:invitation:view:' . $inv->getId()->toRfc4122())->row();
        }

        $builder->button($this->translator->trans('common.back'), 'family:members')->row();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: implode("\n", $lines),
            replyMarkup: $builder->toArray()
        );
    }

    private function getStatusLabel(Invitation $inv, int $usesCount): string
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
