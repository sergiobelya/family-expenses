<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Application\Command\UpdateFamilySettingsCommand;
use App\Family\Application\Command\UpdateFamilySettingsHandler;
use App\Identity\Application\Command\ChangeUserLanguageCommand;
use App\Identity\Application\Command\ChangeUserLanguageHandler;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsFlowHandler implements FlowHandlerInterface
{
    private const string FLOW_NAME = 'settings_family_edit';

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private ChangeUserLanguageHandler $changeUserLanguageHandler,
        private UpdateFamilySettingsHandler $updateFamilySettingsHandler,
        private UserInteractionStateRepository $stateRepository,
        private TranslatorInterface $translator,
        private LocaleAwareInterface $localeAwareTranslator
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->text === $this->translator->trans('main_menu.settings') || $update->text === '/settings') {
            return true;
        }

        if (str_starts_with($update->callbackData ?? '', 'settings:')) {
            return true;
        }

        $state = $this->stateRepository->get($context->telegramUserId);
        if ($state !== null && $state->flow === self::FLOW_NAME && $update->text !== null && !str_starts_with($update->text, '/')) {
            return true;
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramUserContext $context): void
    {
        $userId = $context->telegramUserId;

        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        // 1. Language selector
        if ($update->callbackData === 'settings:lang:select') {
            $builder = new InlineKeyboardBuilder();
            $languages = [
                'en' => 'English',
                'uk' => 'Українська',
                'pl' => 'Polski',
                'ru' => 'Русский',
            ];

            foreach ($languages as $code => $name) {
                $badge = $context->user->getLanguage() === $code ? ' ✅' : '';
                $builder->button($name . $badge, 'settings:lang:set:' . $code)->row();
            }
            $builder->button($this->translator->trans('common.back'), 'settings:main')->row();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.lang_selector_title'),
                replyMarkup: $builder->toArray()
            );
            return;
        }

        // 2. Set Language
        if (str_starts_with($update->callbackData ?? '', 'settings:lang:set:')) {
            $lang = substr($update->callbackData, strlen('settings:lang:set:'));
            ($this->changeUserLanguageHandler)(new ChangeUserLanguageCommand(
                userId: $context->user->getId(),
                language: $lang
            ));

            $this->localeAwareTranslator->setLocale($lang);

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.lang_changed', ['%{language}' => strtoupper($lang)])
            );

            $this->renderMainSettings($context);
            return;
        }

        // 3. Family Settings View
        if ($update->callbackData === 'settings:family:view') {
            $this->renderFamilySettings($context);
            return;
        }

        // 4. Family Settings Edit: Name Prompt
        if ($update->callbackData === 'settings:family:edit:name') {
            $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'name', []));

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.prompt_enter_name'),
                replyMarkup: (new InlineKeyboardBuilder())
                    ->button($this->translator->trans('common.cancel'), 'settings:family:view')
                    ->toArray()
            );
            return;
        }

        // Handle Name submission
        $state = $this->stateRepository->get($userId);
        if ($state !== null && $state->flow === self::FLOW_NAME && $state->step === 'name' && $update->text !== null && $context->hasActiveFamily()) {
            $newName = trim($update->text);
            $this->stateRepository->clear($userId);

            if (mb_strlen($newName) >= 2) {
                ($this->updateFamilySettingsHandler)(new UpdateFamilySettingsCommand(
                    familyId: $context->activeFamily->getId(),
                    userId: $context->user->getId(),
                    name: $newName
                ));

                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $this->translator->trans('settings.saved')
                );
            }

            $this->renderFamilySettings($context);
            return;
        }

        // 5. Family Settings Edit: Timezone
        if ($update->callbackData === 'settings:family:edit:tz') {
            $kb = (new InlineKeyboardBuilder())
                ->button('Europe/Warsaw', 'settings:family:set:tz:Europe/Warsaw')
                ->button('Europe/Kyiv', 'settings:family:set:tz:Europe/Kyiv')
                ->row()
                ->button('UTC', 'settings:family:set:tz:UTC')
                ->button('Europe/London', 'settings:family:set:tz:Europe/London')
                ->row()
                ->button($this->translator->trans('common.back'), 'settings:family:view')
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.prompt_select_timezone'),
                replyMarkup: $kb
            );
            return;
        }

        if (str_starts_with($update->callbackData ?? '', 'settings:family:set:tz:') && $context->hasActiveFamily()) {
            $tz = substr($update->callbackData, strlen('settings:family:set:tz:'));
            ($this->updateFamilySettingsHandler)(new UpdateFamilySettingsCommand(
                familyId: $context->activeFamily->getId(),
                userId: $context->user->getId(),
                timezone: $tz
            ));

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.saved')
            );
            $this->renderFamilySettings($context);
            return;
        }

        // 6. Family Settings Edit: Default Currency
        if ($update->callbackData === 'settings:family:edit:def_curr') {
            $kb = (new InlineKeyboardBuilder())
                ->button('PLN', 'settings:family:set:def_curr:PLN')
                ->button('UAH', 'settings:family:set:def_curr:UAH')
                ->row()
                ->button('EUR', 'settings:family:set:def_curr:EUR')
                ->button('USD', 'settings:family:set:def_curr:USD')
                ->row()
                ->button($this->translator->trans('common.back'), 'settings:family:view')
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.prompt_select_currency'),
                replyMarkup: $kb
            );
            return;
        }

        if (str_starts_with($update->callbackData ?? '', 'settings:family:set:def_curr:') && $context->hasActiveFamily()) {
            $curr = substr($update->callbackData, strlen('settings:family:set:def_curr:'));
            ($this->updateFamilySettingsHandler)(new UpdateFamilySettingsCommand(
                familyId: $context->activeFamily->getId(),
                userId: $context->user->getId(),
                defaultExpenseCurrency: $curr
            ));

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.saved')
            );
            $this->renderFamilySettings($context);
            return;
        }

        // 7. Family Settings Edit: Reporting Currency
        if ($update->callbackData === 'settings:family:edit:rep_curr') {
            $kb = (new InlineKeyboardBuilder())
                ->button('PLN', 'settings:family:set:rep_curr:PLN')
                ->button('UAH', 'settings:family:set:rep_curr:UAH')
                ->row()
                ->button('EUR', 'settings:family:set:rep_curr:EUR')
                ->button('USD', 'settings:family:set:rep_curr:USD')
                ->row()
                ->button($this->translator->trans('common.back'), 'settings:family:view')
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.prompt_select_currency'),
                replyMarkup: $kb
            );
            return;
        }

        if (str_starts_with($update->callbackData ?? '', 'settings:family:set:rep_curr:') && $context->hasActiveFamily()) {
            $curr = substr($update->callbackData, strlen('settings:family:set:rep_curr:'));
            ($this->updateFamilySettingsHandler)(new UpdateFamilySettingsCommand(
                familyId: $context->activeFamily->getId(),
                userId: $context->user->getId(),
                reportingCurrency: $curr
            ));

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.saved')
            );
            $this->renderFamilySettings($context);
            return;
        }

        // 8. Family Settings Edit: Provider
        if ($update->callbackData === 'settings:family:edit:provider') {
            $kb = (new InlineKeyboardBuilder())
                ->button('NBP (Poland)', 'settings:family:set:provider:nbp')
                ->button('NBU (Ukraine)', 'settings:family:set:provider:nbu')
                ->row()
                ->button('ECB (Europe)', 'settings:family:set:provider:ecb')
                ->button('Manual', 'settings:family:set:provider:manual')
                ->row()
                ->button($this->translator->trans('common.back'), 'settings:family:view')
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.prompt_select_provider'),
                replyMarkup: $kb
            );
            return;
        }

        if (str_starts_with($update->callbackData ?? '', 'settings:family:set:provider:') && $context->hasActiveFamily()) {
            $prov = substr($update->callbackData, strlen('settings:family:set:provider:'));
            ($this->updateFamilySettingsHandler)(new UpdateFamilySettingsCommand(
                familyId: $context->activeFamily->getId(),
                userId: $context->user->getId(),
                defaultExchangeRateProvider: $prov
            ));

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('settings.saved')
            );
            $this->renderFamilySettings($context);
            return;
        }

        // Default: Render Main Settings
        $this->renderMainSettings($context);
    }

    private function renderMainSettings(TelegramUserContext $context): void
    {
        $lines = [
            $this->translator->trans('settings.title'),
            '',
            $this->translator->trans('settings.user_section', [
                '%{language}' => strtoupper($context->user->getLanguage()),
            ]),
        ];

        $builder = new InlineKeyboardBuilder();
        $builder->button($this->translator->trans('settings.change_lang_btn'), 'settings:lang:select')->row();

        if ($context->hasActiveFamily()) {
            $family = $context->activeFamily;
            $lines[] = '';
            $lines[] = $this->translator->trans('settings.family_section', [
                '%{family_name}' => htmlspecialchars($family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                '%{timezone}' => (string) $family->getTimezone(),
                '%{default_currency}' => (string) $family->getDefaultExpenseCurrency(),
                '%{reporting_currency}' => (string) $family->getReportingCurrency(),
                '%{provider}' => $family->getDefaultExchangeRateProvider() ?? 'nbp',
            ]);

            $builder->button($this->translator->trans('settings.family_settings_btn'), 'settings:family:view')->row();
        }

        $builder->button($this->translator->trans('common.main_menu'), 'nav:main_menu')->row();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: implode("\n", $lines),
            replyMarkup: $builder->toArray()
        );
    }

    private function renderFamilySettings(TelegramUserContext $context): void
    {
        $family = $context->activeFamily;
        if ($family === null) {
            $this->renderMainSettings($context);
            return;
        }

        $lines = [
            $this->translator->trans('settings.family_settings_title', [
                '%{family_name}' => htmlspecialchars($family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ]),
            '',
            sprintf('• <b>%s:</b> %s', $this->translator->trans('settings.edit_name_btn'), htmlspecialchars($family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            sprintf('• <b>%s:</b> <code>%s</code>', $this->translator->trans('settings.edit_timezone_btn'), (string) $family->getTimezone()),
            sprintf('• <b>%s:</b> <code>%s</code>', $this->translator->trans('settings.edit_default_currency_btn'), (string) $family->getDefaultExpenseCurrency()),
            sprintf('• <b>%s:</b> <code>%s</code>', $this->translator->trans('settings.edit_reporting_currency_btn'), (string) $family->getReportingCurrency()),
            sprintf('• <b>%s:</b> <code>%s</code>', $this->translator->trans('settings.edit_provider_btn'), $family->getDefaultExchangeRateProvider() ?? 'nbp'),
        ];

        $kb = (new InlineKeyboardBuilder())
            ->button($this->translator->trans('settings.edit_name_btn'), 'settings:family:edit:name')
            ->button($this->translator->trans('settings.edit_timezone_btn'), 'settings:family:edit:tz')
            ->row()
            ->button($this->translator->trans('settings.edit_default_currency_btn'), 'settings:family:edit:def_curr')
            ->button($this->translator->trans('settings.edit_reporting_currency_btn'), 'settings:family:edit:rep_curr')
            ->row()
            ->button($this->translator->trans('settings.edit_provider_btn'), 'settings:family:edit:provider')
            ->row()
            ->button($this->translator->trans('common.back'), 'settings:main')
            ->toArray();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: implode("\n", $lines),
            replyMarkup: $kb
        );
    }
}
