<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsCurrencyStep;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsNameStep;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsProviderStep;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsStepInterface;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsTimezoneStep;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use Closure;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FamilySettingsFlowHandler implements FamilyContextFlowHandlerInterface
{
    private const string FLOW_NAME = 'settings_family_edit';

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private MessageBusDispatcherInterface $messageBusDispatcher,
        private UserInteractionStateRepository $stateRepository,
        private TimezoneSelector $timezoneSelector,
        private TranslatorInterface $translator,
        private SettingsMenuRenderer $settingsMenuRenderer,
        private MapExceptionToTranslationKey $exceptionToTranslation
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if (
            $context instanceof TelegramFamilyMemberContext
            && ($update->text === $this->translator->trans('main_menu.settings') || $update->text === '/settings')
        ) {
            return true;
        }

        if (str_starts_with($update->callbackData ?? '', CallbackActions::SETTINGS_FAMILY_PREFIX)) {
            return $context instanceof TelegramFamilyMemberContext;
        }

        if ($update->callbackData === CallbackActions::SETTINGS_MAIN) {
            return $context instanceof TelegramFamilyMemberContext;
        }

        if (!($context instanceof TelegramFamilyMemberContext)) {
            return false;
        }

        $state = $this->stateRepository->get($context->telegramUserId);
        if (
            $state !== null
            && (
                $state->flow === self::FLOW_NAME
                || ($state->flow === FamilySettingsTimezoneStep::FLOW_TIMEZONE_SELECTOR && $state->step === 'search')
            )
            && $update->text !== null
            && !str_starts_with($update->text, '/')
        ) {
            return true;
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramFamilyMemberContext $context): void
    {
        $userId = $context->telegramUserId;

        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        // Settings menu text command
        if (
            $update->text === $this->translator->trans('main_menu.settings')
            || $update->text === '/settings'
        ) {
            $this->settingsMenuRenderer->render($context);
            return;
        }

        $state = $this->stateRepository->get($userId);

        // Common callbacks
        if ($update->callbackData === CallbackActions::SETTINGS_FAMILY_VIEW) {
            $this->clearTimezoneSelectorState($userId, $state);
            $this->renderFamilySettings($context);
            return;
        }

        if ($update->callbackData === CallbackActions::SETTINGS_MAIN) {
            $this->clearTimezoneSelectorState($userId, $state);
            $this->settingsMenuRenderer->render($context);
            return;
        }

        // Delegate to step classes
        $renderFamilySettings = Closure::fromCallable([$this, 'renderFamilySettings']);

        $nameStep = new FamilySettingsNameStep(
            $this->telegramClient,
            $this->messageBusDispatcher,
            $this->stateRepository,
            $this->translator,
            $this->exceptionToTranslation,
            $renderFamilySettings
        );

        $timezoneStep = new FamilySettingsTimezoneStep(
            $this->telegramClient,
            $this->messageBusDispatcher,
            $this->stateRepository,
            $this->timezoneSelector,
            $this->translator
        );

        $currencyStep = new FamilySettingsCurrencyStep(
            $this->telegramClient,
            $this->messageBusDispatcher,
            $this->translator,
            $renderFamilySettings
        );

        $providerStep = new FamilySettingsProviderStep(
            $this->telegramClient,
            $this->messageBusDispatcher,
            $this->translator,
            $renderFamilySettings
        );

        // Handle callbacks that don't require state
        if ($state === null) {
            $nameStep->handle($update, $context, new UserInteractionState($userId, self::FLOW_NAME, 'name', []));
            $timezoneStep->handle($update, $context, new UserInteractionState($userId, FamilySettingsTimezoneStep::FLOW_TIMEZONE_SELECTOR, 'select', []));
            $currencyStep->handle($update, $context, new UserInteractionState($userId, self::FLOW_NAME, 'currency', []));
            $providerStep->handle($update, $context, new UserInteractionState($userId, self::FLOW_NAME, 'provider', []));
            return;
        }

        /** @var array<string, FamilySettingsStepInterface> $steps */
        $steps = [
            'name' => $nameStep,
            'timezone' => $timezoneStep,
            'currency' => $currencyStep,
            'provider' => $providerStep,
        ];

        // Route by step
        if (isset($steps[$state->step])) {
            $newState = $steps[$state->step]->handle($update, $context, $state);
            if ($newState !== null) {
                $this->stateRepository->set($newState);
            }
            return;
        }

        // Timezone selector flow (separate flow name)
        if ($state->flow === FamilySettingsTimezoneStep::FLOW_TIMEZONE_SELECTOR) {
            $newState = $timezoneStep->handle($update, $context, $state);
            if ($newState !== null) {
                $this->stateRepository->set($newState);
            }
            return;
        }

        $this->settingsMenuRenderer->render($context);
    }

    private function clearTimezoneSelectorState(int $userId, ?UserInteractionState $state): void
    {
        if ($state?->flow === FamilySettingsTimezoneStep::FLOW_TIMEZONE_SELECTOR) {
            $this->stateRepository->clear($userId);
        }
    }

    private function renderFamilySettings(TelegramFamilyMemberContext $context): void
    {
        $family = $context->activeFamily;

        $lines = [
            $this->translator->trans('settings.family_settings_title', [
                '%{family_name}' => htmlspecialchars((string) $family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ]),
            '',
            sprintf('• <b>%s:</b> %s', $this->translator->trans('settings.edit_name_btn'), htmlspecialchars((string) $family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
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
