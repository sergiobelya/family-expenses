<?php

declare(strict_types=1);

namespace App\Presentation\Telegram\Flow\FamilyInvitations;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Family\Application\Query\GetFamilyInvitationsQuery;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ListInvitationScreen
{
    public function __construct(
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private InvitationUseRepositoryInterface $invitationUseRepository,
        private GetInvitationStatusLabel $getStatusLabel,
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator
    ) {}

    public function renderList(TelegramFamilyMemberContext $context): void
    {
        $activeFamily = $context->activeFamily;
        $invitations = $this->messageBusDispatcher->dispatchQuery(new GetFamilyInvitationsQuery(
            $activeFamily->getId(),
            $context->user->getId()
        ));

        $builder = new InlineKeyboardBuilder();
        $builder->button($this->translator->trans('family.members.invite_btn'), CallbackActions::FAMILY_INVITE_CREATE)->row();

        if (empty($invitations)) {
            $builder->button($this->translator->trans('common.back'), CallbackActions::FAMILY_MEMBERS)->row();
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.invite.empty_list'),
                replyMarkup: $builder->toArray()
            );
            return;
        }

        $lines = [
            $this->translator->trans('family.invite.list_title', [
                '%{family_name}' => htmlspecialchars((string) $activeFamily->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ]),
            '',
        ];

        foreach ($invitations as $inv) {
            $usesCount = $this->invitationUseRepository->countByInvitationId($activeFamily->getId(), $inv->getId());
            $status = ($this->getStatusLabel)($inv, $usesCount);

            $idShort = substr($inv->getId()->toRfc4122(), 0, 8);
            $lines[] = $this->translator->trans('family.invite.item', [
                '%{id}' => $idShort,
                '%{status}' => $status,
                '%{uses}' => $usesCount,
                '%{max_uses}' => $inv->getMaxUses() ?? '∞',
                '%{expires_at}' => $inv->getExpiresAt()->format('m-d H:i'),
            ]);

            $builder->button(sprintf('#%s (%s)', $idShort, $status), CallbackActions::FAMILY_INVITATION_VIEW_PREFIX . $inv->getId()->toRfc4122())->row();
        }

        $builder->button($this->translator->trans('common.back'), CallbackActions::FAMILY_MEMBERS)->row();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: implode("\n", $lines),
            replyMarkup: $builder->toArray()
        );
    }

}
