<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Domain\Exception\InvalidValueException;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Application\Command\CreateFamilyCommand;
use App\Family\Application\Command\CreateFamilyHandler;
use App\Family\Domain\ValueObject\FamilyName;
use App\Presentation\Telegram\CallbackActions;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\MapExceptionToTranslationKey;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\ExchangeRateProviderOptions;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use Symfony\Contracts\Translation\TranslatorInterface;

class FamilyCreationWizardHandler implements UserContextFlowHandlerInterface
{
    private const string FLOW_NAME = 'family_create';

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private UserInteractionStateRepository $stateRepository,
        private CreateFamilyHandler $createFamilyHandler,
        private MainMenuKeyboard $mainMenuKeyboard,
        private TimezoneSelector $timezoneSelector,
        private TranslatorInterface $translator,
        private MapExceptionToTranslationKey $exceptionToTranslation
    ) {}

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
        $userId = $context->telegramUserId;

        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        if ($update->callbackData === CallbackActions::FAMILY_CREATE_CANCEL) {
            $this->stateRepository->clear($userId);
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.cancelled')
            );
            return;
        }

        if ($update->callbackData === CallbackActions::FAMILY_CREATE_START) {
            $state = new UserInteractionState($userId, self::FLOW_NAME, 'name', []);
            $this->stateRepository->set($state);

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.step_name'),
                replyMarkup: (new InlineKeyboardBuilder())
                    ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
                    ->toArray()
            );
            return;
        }

        $state = $this->stateRepository->get($userId);
        if ($state === null || $state->flow !== self::FLOW_NAME) {
            return;
        }

        $data = $state->data;
        $step = $state->step;

        if ($step === 'timezone_search' && $update->text !== null && !str_starts_with($update->text, '/')) {
            $this->renderTimezoneSelector($context, $data, trim($update->text));
            return;
        }

        if ($step === 'name' && $update->text !== null) {
            $name = trim($update->text);
            try {
                $familyName = FamilyName::fromString($name);
            } catch (InvalidValueException $e) {
                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $this->translator->trans(($this->exceptionToTranslation)($e))
                );
                return;
            }
            $data['name'] = (string) $familyName;
            $this->renderTimezoneSelector($context, $data);
            return;
        }

        if ($step === 'timezone' && $update->callbackData === CallbackActions::FAMILY_CREATE_TIMEZONE_SEARCH) {
            $this->stateRepository->set(new UserInteractionState(
                $userId,
                self::FLOW_NAME,
                'timezone_search',
                $data + ['query' => '', 'page' => 0]
            ));
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('selector.search_prompt'),
                replyMarkup: (new InlineKeyboardBuilder())
                    ->button($this->translator->trans('common.back'), CallbackActions::FAMILY_CREATE_TIMEZONE_SELECT)
                    ->toArray()
            );
            return;
        }

        if (
            in_array($step, ['timezone', 'timezone_search'], true)
            && $update->callbackData === CallbackActions::FAMILY_CREATE_TIMEZONE_SELECT
        ) {
            $this->renderTimezoneSelector(
                $context,
                $data,
                (string) ($data['query'] ?? ''),
                (int) ($data['page'] ?? 0)
            );
            return;
        }

        if (
            in_array($step, ['timezone', 'timezone_search'], true)
            && $update->callbackData === CallbackActions::FAMILY_CREATE_TIMEZONE_CLEAR
        ) {
            $this->renderTimezoneSelector($context, $data);
            return;
        }

        if (
            in_array($step, ['timezone', 'timezone_search'], true)
            && preg_match('/^family:create:timezone:page:(\d+)$/', $update->callbackData ?? '', $matches) === 1
        ) {
            $this->renderTimezoneSelector($context, $data, (string) ($data['query'] ?? ''), (int) $matches[1]);
            return;
        }

        if (
            in_array($step, ['timezone', 'timezone_search'], true)
            && (str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_TIMEZONE_SET_PREFIX)
                || str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_TZ_PREFIX))
        ) {
            $prefix = str_starts_with($update->callbackData, CallbackActions::FAMILY_CREATE_TIMEZONE_SET_PREFIX)
                ? CallbackActions::FAMILY_CREATE_TIMEZONE_SET_PREFIX
                : CallbackActions::FAMILY_CREATE_TZ_PREFIX;
            $tz = substr($update->callbackData, strlen($prefix));
            if (!$this->timezoneSelector->contains($tz)) {
                return;
            }

            $data['timezone'] = $tz;
            $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'default_currency', $data));

            $kb = (new InlineKeyboardBuilder())
                ->button('PLN', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX . 'PLN')
                ->button('UAH', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX . 'UAH')
                ->row()
                ->button('EUR', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX . 'EUR')
                ->button('USD', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX . 'USD')
                ->row()
                ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.step_default_currency'),
                replyMarkup: $kb
            );
            return;
        }

        if ($step === 'default_currency' && str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX)) {
            $curr = substr($update->callbackData, strlen(CallbackActions::FAMILY_CREATE_DEF_CURR_PREFIX));
            $data['default_currency'] = $curr;
            $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'reporting_currency', $data));

            $kb = (new InlineKeyboardBuilder())
                ->button('PLN', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX . 'PLN')
                ->button('UAH', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX . 'UAH')
                ->row()
                ->button('EUR', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX . 'EUR')
                ->button('USD', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX . 'USD')
                ->row()
                ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.step_reporting_currency'),
                replyMarkup: $kb
            );
            return;
        }

        if ($step === 'reporting_currency' && str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX)) {
            $curr = substr($update->callbackData, strlen(CallbackActions::FAMILY_CREATE_REP_CURR_PREFIX));
            $data['reporting_currency'] = $curr;
            $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'provider', $data));

            $builder = new InlineKeyboardBuilder();
            foreach (array_chunk(ExchangeRateProviderOptions::ALL, 2, true) as $providers) {
                foreach ($providers as $provider => $label) {
                    $builder->button($label, CallbackActions::FAMILY_CREATE_PROVIDER_PREFIX . $provider);
                }
                $builder->row();
            }
            $kb = $builder
                ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.step_provider'),
                replyMarkup: $kb
            );
            return;
        }

        if ($step === 'provider' && str_starts_with($update->callbackData ?? '', CallbackActions::FAMILY_CREATE_PROVIDER_PREFIX)) {
            $provider = substr($update->callbackData, strlen(CallbackActions::FAMILY_CREATE_PROVIDER_PREFIX));
            if (!ExchangeRateProviderOptions::contains($provider)) {
                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $this->translator->trans('family.create.unsupported_provider')
                );
                return;
            }

            $data['provider'] = $provider;
            $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'confirm', $data));

            $kb = (new InlineKeyboardBuilder())
                ->button($this->translator->trans('common.confirm'), CallbackActions::FAMILY_CREATE_SUBMIT)
                ->button($this->translator->trans('common.cancel'), CallbackActions::FAMILY_CREATE_CANCEL)
                ->toArray();

            $confirmText = $this->translator->trans('family.create.step_confirm', [
                '%{name}' => htmlspecialchars((string) $data['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                '%{timezone}' => $data['timezone'],
                '%{default_currency}' => $data['default_currency'],
                '%{reporting_currency}' => $data['reporting_currency'],
                '%{provider}' => $data['provider'],
            ]);

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $confirmText,
                replyMarkup: $kb
            );
            return;
        }

        if ($step === 'confirm' && $update->callbackData === CallbackActions::FAMILY_CREATE_SUBMIT) {
            try {
                $family = ($this->createFamilyHandler)(new CreateFamilyCommand(
                    userId: $context->user->getId(),
                    name: (string) $data['name'],
                    timezone: (string) $data['timezone'],
                    defaultExpenseCurrency: (string) $data['default_currency'],
                    reportingCurrency: (string) $data['reporting_currency'],
                    defaultExchangeRateProvider: (string) ($data['provider'] ?? 'nbp')
                ));

                $this->stateRepository->clear($userId);

                $successMsg = $this->translator->trans('family.create.created_success', [
                    '%{name}' => htmlspecialchars($family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                ]);

                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $successMsg,
                    replyMarkup: $this->mainMenuKeyboard->build(false)
                );
            } catch (InvalidValueException $e) {
                $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'name', $data));
                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $this->translator->trans(($this->exceptionToTranslation)($e))
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTimezoneSelector(
        TelegramUserContext $context,
        array $data,
        string $query = '',
        int $page = 0
    ): void {
        $suggestedTimezone = $context instanceof TelegramFamilyMemberContext
            ? (string) $context->activeFamily->getTimezone()
            : 'UTC';
        $selection = $this->timezoneSelector->build(
            callbackPrefix: 'family:create:timezone',
            selectedValue: $suggestedTimezone,
            query: $query,
            page: $page,
            backLabel: $this->translator->trans('common.cancel'),
            backCallback: CallbackActions::FAMILY_CREATE_CANCEL,
            preferredTimezone: $suggestedTimezone
        );
        $page = $selection['page'];
        $data['query'] = $query;
        $data['page'] = $page;
        unset($data['timezone']);

        $this->stateRepository->set(new UserInteractionState(
            $context->telegramUserId,
            self::FLOW_NAME,
            'timezone',
            $data
        ));

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: !$selection['has_results']
                ? $this->translator->trans('selector.no_results')
                : $this->translator->trans('family.create.step_timezone'),
            replyMarkup: $selection['keyboard']
        );
    }
}
