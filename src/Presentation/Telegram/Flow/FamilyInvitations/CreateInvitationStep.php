<?php

namespace App\Presentation\Telegram\Flow\FamilyInvitations;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Family\Application\Command\CreateInvitationCommand;
use App\Family\Domain\Entity\Invitation;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CreateInvitationStep implements FamilyInvitationsStepInterface
{
    public function __construct(
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator,
        #[Autowire('%app.telegram_bot_username%')]
        private string $botUsername,
    ) {}

    public function handle(
        TelegramUpdate $update,
        TelegramFamilyMemberContext $context
    ): void {
        $activeFamily = $context->activeFamily;

        $result = $this->messageBusDispatcher->dispatchCommand(new CreateInvitationCommand(
            familyId: $activeFamily->getId(),
            maxUses: 1,
            expiresInHours: 48,
            userId: $context->user->getId()
        ));

        $plainToken = $result['raw_token'];
        /** @var Invitation $invitation */
        $invitation = $result['invitation'];

        $deepLink = sprintf('https://t.me/%s?start=invite_%s', $this->botUsername, $plainToken);

        $msg = $this->translator->trans('family.invite.create_success', [
            '%{family_name}' => htmlspecialchars((string) $activeFamily->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '%{link}' => $deepLink,
            '%{max_uses}' => $invitation->getMaxUses() ?? '∞',
        ]);

        $kb = new InlineKeyboardBuilder()
            ->button($this->translator->trans('family.members.invitations_btn'), CallbackActions::FAMILY_INVITATIONS_LIST)
            ->button($this->translator->trans('common.main_menu'), CallbackActions::NAV_MAIN_MENU)
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $msg,
            replyMarkup: $kb
        );
    }
}
