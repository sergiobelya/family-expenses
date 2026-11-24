<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Family\Application\Query\GetFamilyMembersQuery;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FamilyMembersFlowHandler implements FamilyContextFlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private TranslatorInterface $translator
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->text === $this->translator->trans('main_menu.members')) {
            return true;
        }

        if ($update->callbackData === CallbackActions::FAMILY_MEMBERS) {
            return true;
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        $activeFamily = $context->activeFamily;

        $members = $this->messageBusDispatcher->dispatchQuery(new GetFamilyMembersQuery(
            $activeFamily->getId(),
            $context->user->getId()
        ));

        $lines = [
            $this->translator->trans('family.members.title', [
                '%{family_name}' => htmlspecialchars($activeFamily->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ]),
            '',
        ];

        foreach ($members as $member) {
            $isYou = $member->getUserId()->equals($context->user->getId());
            $badge = $isYou ? $this->translator->trans('family.members.you_badge') : '';

            $lines[] = $this->translator->trans('family.members.member_row', [
                '%{user_id}' => substr($member->getUserId()->toRfc4122(), 0, 8),
                '%{you}' => $badge,
                '%{joined_at}' => $member->getJoinedAt()->format('Y-m-d'),
            ]);
        }

        $kb = (new InlineKeyboardBuilder())
            ->button($this->translator->trans('family.members.invite_btn'), CallbackActions::FAMILY_INVITE_CREATE)
            ->button($this->translator->trans('family.members.invitations_btn'), CallbackActions::FAMILY_INVITATIONS_LIST)
            ->row()
            ->button($this->translator->trans('common.main_menu'), CallbackActions::NAV_MAIN_MENU)
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: implode("\n", $lines),
            replyMarkup: $kb
        );
    }

}
