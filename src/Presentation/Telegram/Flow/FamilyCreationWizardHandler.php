<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Application\Command\CreateFamilyCommand;
use App\Family\Application\Command\CreateFamilyHandler;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use App\Presentation\Telegram\Keyboard\TimezoneSelector;
use Symfony\Contracts\Translation\TranslatorInterface;

class FamilyCreationWizardHandler implements FlowHandlerInterface
{
    private const string FLOW_NAME = 'family_create';

    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private UserInteractionStateRepository $stateRepository,
        private CreateFamilyHandler $createFamilyHandler,
        private MainMenuKeyboard $mainMenuKeyboard,
        private TimezoneSelector $timezoneSelector,
        private TranslatorInterface $translator
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->callbackData === 'family:create:start' || $update->callbackData === 'family:create:cancel') {
            return true;
        }

        if (str_starts_with($update->callbackData ?? '', 'family:create:')) {
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

        if ($update->callbackData === 'family:create:cancel') {
            $this->stateRepository->clear($userId);
            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.cancelled')
            );
            return;
        }

        if ($update->callbackData === 'family:create:start') {
            $state = new UserInteractionState($userId, self::FLOW_NAME, 'name', []);
            $this->stateRepository->set($state);

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.step_name'),
                replyMarkup: (new InlineKeyboardBuilder())
                    ->button($this->translator->trans('common.cancel'), 'family:create:cancel')
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
            if (mb_strlen($name) < 2) {
                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $this->translator->trans('family.create.step_name')
                );
                return;
            }

            $data['name'] = $name;
            $this->renderTimezoneSelector($context, $data);
            return;
        }

        if ($step === 'timezone' && $update->callbackData === 'family:create:timezone:search') {
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
                    ->button($this->translator->trans('common.back'), 'family:create:timezone:select')
                    ->toArray()
            );
            return;
        }

        if (
            in_array($step, ['timezone', 'timezone_search'], true)
            && $update->callbackData === 'family:create:timezone:select'
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
            && $update->callbackData === 'family:create:timezone:clear'
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
            && (str_starts_with($update->callbackData ?? '', 'family:create:timezone:set:')
                || str_starts_with($update->callbackData ?? '', 'family:create:tz:'))
        ) {
            $prefix = str_starts_with($update->callbackData, 'family:create:timezone:set:')
                ? 'family:create:timezone:set:'
                : 'family:create:tz:';
            $tz = substr($update->callbackData, strlen($prefix));
            if (!$this->timezoneSelector->contains($tz)) {
                return;
            }

            $data['timezone'] = $tz;
            $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'default_currency', $data));

            $kb = (new InlineKeyboardBuilder())
                ->button('PLN', 'family:create:def_curr:PLN')
                ->button('UAH', 'family:create:def_curr:UAH')
                ->row()
                ->button('EUR', 'family:create:def_curr:EUR')
                ->button('USD', 'family:create:def_curr:USD')
                ->row()
                ->button($this->translator->trans('common.cancel'), 'family:create:cancel')
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.step_default_currency'),
                replyMarkup: $kb
            );
            return;
        }

        if ($step === 'default_currency' && str_starts_with($update->callbackData ?? '', 'family:create:def_curr:')) {
            $curr = substr($update->callbackData, strlen('family:create:def_curr:'));
            $data['default_currency'] = $curr;
            $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'reporting_currency', $data));

            $kb = (new InlineKeyboardBuilder())
                ->button('PLN', 'family:create:rep_curr:PLN')
                ->button('UAH', 'family:create:rep_curr:UAH')
                ->row()
                ->button('EUR', 'family:create:rep_curr:EUR')
                ->button('USD', 'family:create:rep_curr:USD')
                ->row()
                ->button($this->translator->trans('common.cancel'), 'family:create:cancel')
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.step_reporting_currency'),
                replyMarkup: $kb
            );
            return;
        }

        if ($step === 'reporting_currency' && str_starts_with($update->callbackData ?? '', 'family:create:rep_curr:')) {
            $curr = substr($update->callbackData, strlen('family:create:rep_curr:'));
            $data['reporting_currency'] = $curr;
            $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'provider', $data));

            $kb = (new InlineKeyboardBuilder())
                ->button('NBP (Poland)', 'family:create:provider:nbp')
                ->button('NBU (Ukraine)', 'family:create:provider:nbu')
                ->row()
                ->button('ECB (Europe)', 'family:create:provider:ecb')
                ->button('Manual', 'family:create:provider:manual')
                ->row()
                ->button($this->translator->trans('common.cancel'), 'family:create:cancel')
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $this->translator->trans('family.create.step_provider'),
                replyMarkup: $kb
            );
            return;
        }

        if ($step === 'provider' && str_starts_with($update->callbackData ?? '', 'family:create:provider:')) {
            $provider = substr($update->callbackData, strlen('family:create:provider:'));
            $data['provider'] = $provider;
            $this->stateRepository->set(new UserInteractionState($userId, self::FLOW_NAME, 'confirm', $data));

            $kb = (new InlineKeyboardBuilder())
                ->button($this->translator->trans('common.confirm'), 'family:create:submit')
                ->button($this->translator->trans('common.cancel'), 'family:create:cancel')
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

        if ($step === 'confirm' && $update->callbackData === 'family:create:submit') {
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
        $suggestedTimezone = $context->activeFamily?->getTimezone()->value ?? 'UTC';
        $selection = $this->timezoneSelector->build(
            callbackPrefix: 'family:create:timezone',
            selectedValue: $suggestedTimezone,
            query: $query,
            page: $page,
            backLabel: $this->translator->trans('common.cancel'),
            backCallback: 'family:create:cancel',
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
