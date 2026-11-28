<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsCurrencyStep;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsNameStep;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsProviderStep;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsRenderer;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsStepInterface;
use App\Presentation\Telegram\Flow\FamilySettings\FamilySettingsTimezoneStep;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FamilySettingsFlowHandler implements FamilyContextFlowHandlerInterface
{
    private const string FLOW_NAME = 'settings_family_edit';

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private FamilySettingsNameStep $nameStep,
        private FamilySettingsTimezoneStep $timezoneStep,
        private FamilySettingsCurrencyStep $currencyStep,
        private FamilySettingsProviderStep $providerStep,
        private UserInteractionStateRepository $stateRepository,
        private TranslatorInterface $translator,
        private FamilySettingsRenderer $familySettingsRenderer,
        private SettingsMenuRenderer $settingsMenuRenderer,
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
            $this->familySettingsRenderer->render($context);
            return;
        }

        if ($update->callbackData === CallbackActions::SETTINGS_MAIN) {
            $this->clearTimezoneSelectorState($userId, $state);
            $this->settingsMenuRenderer->render($context);
            return;
        }

        // Handle callbacks that don't require state
        if ($state === null) {
            $this->nameStep->handle($update, $context, new UserInteractionState($userId, self::FLOW_NAME, 'name', []));
            $this->timezoneStep->handle($update, $context, new UserInteractionState($userId, FamilySettingsTimezoneStep::FLOW_TIMEZONE_SELECTOR, 'select', []));
            $this->currencyStep->handle($update, $context, new UserInteractionState($userId, self::FLOW_NAME, 'currency', []));
            $this->providerStep->handle($update, $context, new UserInteractionState($userId, self::FLOW_NAME, 'provider', []));
            return;
        }

        /** @var array<string, FamilySettingsStepInterface> $steps */
        $steps = [
            'name' => $this->nameStep,
            'timezone' => $this->timezoneStep,
            'currency' => $this->currencyStep,
            'provider' => $this->providerStep,
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
            $newState = $this->timezoneStep->handle($update, $context, $state);
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
}
