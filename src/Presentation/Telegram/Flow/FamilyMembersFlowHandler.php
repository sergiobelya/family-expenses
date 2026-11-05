<?php

namespace App\Presentation\Telegram\Flow;

use App\Family\Application\Query\GetFamilyMembersHandler;
use App\Family\Application\Query\GetFamilyMembersQuery;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

class FamilyMembersFlowHandler implements FlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private GetFamilyMembersHandler $getFamilyMembersHandler,
        private TranslatorInterface $translator
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if (!$context->hasActiveFamily()) {
            return false;
        }

        if ($update->text === $this->translator->trans('main_menu.members')) {
            return true;
        }

        if ($update->callbackData === 'family:members') {
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

        $members = ($this->getFamilyMembersHandler)(new GetFamilyMembersQuery($activeFamily->getId(), $context->user->getId()));

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
            ->button($this->translator->trans('family.members.invite_btn'), 'family:invite:create')
            ->button($this->translator->trans('family.members.invitations_btn'), 'family:invitations:list')
            ->row()
            ->button($this->translator->trans('common.main_menu'), 'nav:main_menu')
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: implode("\n", $lines),
            replyMarkup: $kb
        );
    }
}
