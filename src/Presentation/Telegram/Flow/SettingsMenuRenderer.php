<?php

namespace App\Presentation\Telegram\Flow;

use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsMenuRenderer
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator
    ) {}

    public function render(TelegramUserContext $context): void
    {
        $lines = [
            $this->translator->trans('settings.title'),
            '',
            $this->translator->trans('settings.user_section', [
                '%{language}' => strtoupper($context->user->getLanguage()),
            ]),
        ];

        $builder = new InlineKeyboardBuilder();
        $builder->button($this->translator->trans('settings.change_lang_btn'), CallbackActions::SETTINGS_LANG_SELECT)->row();

        if ($context instanceof TelegramFamilyMemberContext) {
            $family = $context->activeFamily;
            $lines[] = '';
            $lines[] = $this->translator->trans('settings.family_section', [
                '%{family_name}' => htmlspecialchars($family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                '%{timezone}' => (string) $family->getTimezone(),
                '%{default_currency}' => (string) $family->getDefaultExpenseCurrency(),
                '%{reporting_currency}' => (string) $family->getReportingCurrency(),
                '%{provider}' => $family->getDefaultExchangeRateProvider(),
            ]);

            $builder->button($this->translator->trans('settings.family_settings_btn'), CallbackActions::SETTINGS_FAMILY_VIEW)->row();
        }

        $builder->button($this->translator->trans('common.main_menu'), CallbackActions::NAV_MAIN_MENU)->row();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: implode("\n", $lines),
            replyMarkup: $builder->toArray()
        );
    }
}
