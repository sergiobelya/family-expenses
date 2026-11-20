<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Application\Command\CreateFamilyHandler;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Flow\FamilyCreation\FamilyConfirmStep;
use App\Presentation\Telegram\Flow\FamilyCreation\FamilyCurrencyStep;
use App\Presentation\Telegram\Flow\FamilyCreation\FamilyNameStep;
use App\Presentation\Telegram\Flow\FamilyCreation\FamilyProviderStep;
use App\Presentation\Telegram\Flow\FamilyCreation\FamilyTimezoneStep;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use Symfony\Contracts\Translation\TranslatorInterface;

class FamilyCreationWizardHandler implements UserContextFlowHandlerInterface
{
    private const string FLOW_NAME = 'family_create';

    private FamilyNameStep $nameStep;
    private FamilyTimezoneStep $timezoneStep;
    private FamilyCurrencyStep $currencyStep;
    private FamilyProviderStep $providerStep;
    private FamilyConfirmStep $confirmStep;

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private UserInteractionStateRepository $stateRepository,
        private CreateFamilyHandler $createFamilyHandler,
        private MainMenuKeyboard $mainMenuKeyboard,
        private TimezoneSelector $timezoneSelector,
        private TranslatorInterface $translator,
        private MapExceptionToTranslationKey $exceptionToTranslation
    ) {
        $this->nameStep = new FamilyNameStep(
            $this->telegramClient,
            $this->stateRepository,
            $this->timezoneSelector,
            $this->translator,
            $this->exceptionToTranslation
        );
        $this->timezoneStep = new FamilyTimezoneStep(
            $this->telegramClient,
            $this->timezoneSelector,
            $this->translator
        );
        $this->currencyStep = new FamilyCurrencyStep(
            $this->telegramClient,
            $this->translator
        );
        $this->providerStep = new FamilyProviderStep(
            $this->telegramClient,
            $this->translator
        );
        $this->confirmStep = new FamilyConfirmStep(
            $this->telegramClient,
            $this->stateRepository,
            $this->createFamilyHandler,
            $this->mainMenuKeyboard,
            $this->translator,
            $this->exceptionToTranslation
        );
    }

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->callbackData === CallbackActions::FAMILY_CREATE_START || $update->callbackData === CallbackActions::FAMILY_CREATE_CANCEL) {
            return true;
        }

        if (str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_PREFIX)) {
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
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        // Cancel
        if ($update->callbackData === CallbackActions::FAMILY_CREATE_CANCEL) {
            $this->stateRepository->clear($context->telegramUserId);
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.cancelled')
            );
            return;
        }

        // Start wizard
        if ($update->callbackData === CallbackActions::FAMILY_CREATE_START) {
            $this->nameStep->start($update, $context);
            return;
        }

        // Get current state
        $state = $this->stateRepository->get($context->telegramUserId);
        if ($state === null || $state->flow !== self::FLOW_NAME) {
            return;
        }

        // Name step (text input only)
        if ($state->step === 'name') {
            $newState = $this->nameStep->handle($update, $context, $state);
            if ($newState !== null) {
                $timezoneState = $this->nameStep->renderTimezoneSelector($context, $newState->data);
                $this->stateRepository->set($timezoneState);
            }
            return;
        }

        // Timezone step
        if (in_array($state->step, ['timezone', 'timezone_search'], true)) {
            $newState = $this->timezoneStep->handle($update, $context, $state);
            if ($newState !== null) {
                $this->stateRepository->set($newState);
                if ($newState->step === 'default_currency') {
                    $this->currencyStep->renderDefaultCurrency($context);
                }
            }
            return;
        }

        // Currency step
        if (in_array($state->step, ['default_currency', 'reporting_currency'], true)) {
            $newState = $this->currencyStep->handle($update, $context, $state);
            if ($newState !== null) {
                $this->stateRepository->set($newState);
                if ($newState->step === 'provider') {
                    $this->providerStep->renderProviderSelector($context);
                }
            }
            return;
        }

        // Provider step
        if ($state->step === 'provider') {
            $newState = $this->providerStep->handle($update, $context, $state);
            if ($newState !== null) {
                $this->stateRepository->set($newState);
                $this->confirmStep->renderConfirmation($context, $newState->data);
            }
            return;
        }

        // Confirm step
        if ($state->step === 'confirm') {
            $this->confirmStep->handle($update, $context, $state);
            return;
        }
    }
}
