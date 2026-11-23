<?php

namespace App\Presentation\Telegram\Flow\FamilySettings;

use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FamilySettingsRenderer
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private TranslatorInterface $translator
    ) {}

    public function render(TelegramFamilyMemberContext $context): void
    {
        $family = $context->activeFamily;

        $escapedFamilyName = htmlspecialchars((string) $family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lines = [
            $this->translator->trans('settings.family_settings_title', [
                '%{family_name}' => $escapedFamilyName,
            ]),
            '',
            sprintf('• <b>%s:</b> %s', $this->translator->trans('settings.edit_name_btn'), $escapedFamilyName),
            sprintf('• <b>%s:</b> <code>%s</code>', $this->translator->trans('settings.edit_timezone_btn'), (string) $family->getTimezone()),
            sprintf('• <b>%s:</b> <code>%s</code>', $this->translator->trans('settings.edit_default_currency_btn'), (string) $family->getDefaultExpenseCurrency()),
            sprintf('• <b>%s:</b> <code>%s</code>', $this->translator->trans('settings.edit_reporting_currency_btn'), (string) $family->getReportingCurrency()),
            sprintf('• <b>%s:</b> <code>%s</code>', $this->translator->trans('settings.edit_provider_btn'), $family->getDefaultExchangeRateProvider()),
        ];

        $kb = (new InlineKeyboardBuilder())
            ->button($this->translator->trans('settings.edit_name_btn'), CallbackActions::SETTINGS_FAMILY_EDIT_NAME)
            ->button($this->translator->trans('settings.edit_timezone_btn'), CallbackActions::SETTINGS_FAMILY_EDIT_TZ)
            ->row()
            ->button($this->translator->trans('settings.edit_default_currency_btn'), CallbackActions::SETTINGS_FAMILY_EDIT_DEF_CURR)
            ->button($this->translator->trans('settings.edit_reporting_currency_btn'), CallbackActions::SETTINGS_FAMILY_EDIT_REP_CURR)
            ->row()
            ->button($this->translator->trans('settings.edit_provider_btn'), CallbackActions::SETTINGS_FAMILY_EDIT_PROVIDER)
            ->row()
            ->button($this->translator->trans('common.back'), CallbackActions::SETTINGS_MAIN)
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: implode("\n", $lines),
            replyMarkup: $kb
        );
    }
}
