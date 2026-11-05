<?php

namespace App\Presentation\Telegram\Flow;

use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

class FamilyOnboardingFlowHandler implements FlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($context->hasActiveFamily()) {
            return false;
        }

        if ($update->text === '/start' || $update->text === '/menu') {
            return true;
        }

        if ($update->callbackData === 'nav:onboarding') {
            return true;
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramUserContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        $title = $this->translator->trans('family.onboarding.title');
        $text = $this->translator->trans('family.onboarding.text');
        $createBtn = $this->translator->trans('family.onboarding.create_family_btn');

        $keyboard = (new InlineKeyboardBuilder())
            ->button($createBtn, 'family:create:start')
            ->toArray();

        $message = sprintf("%s\n\n%s", $title, $text);

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $message,
            replyMarkup: $keyboard
        );
    }
}
